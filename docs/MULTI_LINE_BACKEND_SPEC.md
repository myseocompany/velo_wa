# Spec Backend: Múltiples líneas de WhatsApp por Tenant

> Este documento es para Codex. Solo cubre backend (PHP/Laravel). El frontend se implementa por separado.

## Objetivo

Permitir que cada tenant conecte **N líneas de WhatsApp** (cada una con su propio número y su propia instancia de Evolution API). Hoy la relación es 1:1 — los campos `wa_*` viven directamente en la tabla `tenants`.

---

## Estado actual (1 línea por tenant)

### Tabla `tenants` — campos WhatsApp a extraer

```
wa_instance_id                  varchar unique nullable   -- nombre instancia Evolution API
wa_status                       varchar default 'disconnected'  -- enum: disconnected|qr_pending|connected|banned
wa_phone                        varchar(30) nullable
wa_connected_at                 timestamp nullable
wa_health_consecutive_failures  smallint default 0
wa_health_last_alert_at         timestamp nullable
```

### Archivos clave y su rol actual

| Archivo | Rol |
|---------|-----|
| `app/Models/Tenant.php` | Tiene campos `wa_*` directamente. Método `instanceName()` genera `tenant_{8chars}`. |
| `app/Models/Conversation.php` | Tiene `channel` enum (whatsapp\|manual) pero NO referencia a instancia/línea. |
| `app/Models/Contact.php` | `wa_id`, `phone`, scoped a tenant. Sin referencia a línea. |
| `app/Models/Message.php` | `wa_message_id`, `direction`, `status`. |
| `app/Models/WaHealthLog.php` | Health checks, tiene `tenant_id` + `instance_name`. |
| `app/Services/WhatsAppClientService.php` | Cliente HTTP a Evolution API. `createInstance()`, `sendText()`, `sendMedia()`, `getConnectionState()`, `deleteInstance()`. Método estático `instanceName(tenantId)`. |
| `app/Services/WebhookHandlerService.php` | Recibe webhooks. Resuelve tenant por `wa_instance_id`. Despacha `HandleInboundMessage`. |
| `app/Http/Controllers/WebhookController.php` | Valida API key, busca `Tenant::where('wa_instance_id', $instance)`. |
| `app/Http/Controllers/Api/V1/WhatsAppController.php` | Endpoints: `connect`, `disconnect`, `status`, `health-logs`. Opera sobre el único `wa_instance_id` del tenant. |
| `app/Jobs/HandleInboundMessage.php` | Procesa mensaje entrante. Crea contacto/conversación. Recibe `tenantId` + payload. |
| `app/Jobs/SendWhatsAppMessage.php` | Envía mensaje saliente. Resuelve instancia via `message→conversation→contact→tenant→wa_instance_id`. |
| `app/Jobs/CheckInstanceHealth.php` | Itera `Tenant::whereNotNull('wa_instance_id')`, chequea cada instancia. |
| `app/Actions/WhatsApp/CreateOrUpdateConversation.php` | Busca conversación abierta por `contact_id` + `tenant_id`. Crea si no existe. |
| `app/Actions/WhatsApp/CreateOrUpdateContact.php` | Upsert contacto. `sanitizeResolvedPhone()` usa `$tenant->wa_phone` para filtrar self-messages. |
| `app/Enums/WaStatus.php` | Enum: `Disconnected`, `QrPending`, `Connected`, `Banned`. |
| `app/Enums/Channel.php` | Enum: `WhatsApp`, `Manual`. |

---

## Convenciones del proyecto

- `declare(strict_types=1);` en todos los archivos PHP
- PSR-12
- UUIDs como primary keys
- `tenant_id` en todas las tablas tenant-scoped
- Traits: `HasUuids`, `SoftDeletes`, `BelongsToTenant`
- Backed PHP enums para statuses
- Laravel Actions para lógica de negocio
- Feature tests para endpoints, unit tests para lógica

---

## Implementación requerida

### Fase 1 — Modelo y migraciones

#### 1.1 Crear migración `create_whatsapp_lines_table`

```php
Schema::create('whatsapp_lines', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
    $table->string('label', 100);                          // "Ventas", "Soporte"
    $table->string('instance_id', 255)->unique()->nullable(); // nombre instancia Evolution
    $table->string('status', 30)->default('disconnected');  // WaStatus enum
    $table->string('phone', 30)->nullable();
    $table->timestamp('connected_at')->nullable();
    $table->boolean('is_default')->default(false);
    $table->unsignedSmallInteger('health_consecutive_failures')->default(0);
    $table->timestamp('health_last_alert_at')->nullable();
    $table->timestamps();
    $table->softDeletes();

    $table->index('tenant_id');
    $table->index(['tenant_id', 'is_default']);
    $table->index(['tenant_id', 'status']);
});
```

> **Constraint de default único**: Laravel schema builder no tiene soporte nativo para partial unique indexes, pero PostgreSQL sí. Agregar con raw SQL en la migración:
> ```php
> // up()
> DB::statement('CREATE UNIQUE INDEX whatsapp_lines_tenant_default_unique ON whatsapp_lines (tenant_id) WHERE is_default = true AND deleted_at IS NULL');
>
> // down()
> DB::statement('DROP INDEX IF EXISTS whatsapp_lines_tenant_default_unique');
> ```
> Este constraint es la defensa primaria. Además, usar `lockForUpdate` en la lógica de store/update como defensa en profundidad (ver sección 3.1).

#### 1.2 Crear migración `add_whatsapp_line_id_to_related_tables`

```php
// conversations
$table->foreignUuid('whatsapp_line_id')->nullable()->constrained('whatsapp_lines')->nullOnDelete();
$table->index('whatsapp_line_id');
$table->index(['tenant_id', 'whatsapp_line_id', 'status']); // filtro de conversaciones abiertas por línea

// wa_health_logs
$table->foreignUuid('whatsapp_line_id')->nullable()->constrained('whatsapp_lines')->nullOnDelete();
```

#### 1.3 Crear migración de datos `migrate_tenant_wa_data_to_whatsapp_lines`

Dentro de `DB::transaction`:

1. Para cada tenant con `wa_instance_id IS NOT NULL` **O** que tenga conversaciones con `channel='whatsapp'`:
   - INSERT en `whatsapp_lines`:
     - `label = 'Principal'`, `is_default = true`
     - Si el tenant tiene `wa_instance_id`: copiar `wa_instance_id → instance_id`, `wa_status → status`, `wa_phone → phone`, `wa_connected_at → connected_at`, campos de health.
     - Si el tenant NO tiene `wa_instance_id` (desconectado pero con historial): `instance_id = null`, `status = 'disconnected'`, demás campos null/default.
   - UPDATE `conversations` del tenant donde `channel='whatsapp'` → set `whatsapp_line_id` = id de la línea creada.
   - UPDATE `wa_health_logs` del tenant → set `whatsapp_line_id` = id de la línea creada.

Esto garantiza que conversaciones históricas de tenants desconectados no queden con `whatsapp_line_id = null`.

NO eliminar los campos `wa_*` de tenants. Eso se hará en un release posterior.

#### 1.4 Crear modelo `app/Models/WhatsAppLine.php`

```php
declare(strict_types=1);

namespace App\Models;

use App\Enums\WaStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class WhatsAppLine extends Model
{
    use HasUuids, SoftDeletes, BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'label', 'instance_id', 'status', 'phone',
        'connected_at', 'is_default', 'health_consecutive_failures',
        'health_last_alert_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => WaStatus::class,
            'connected_at' => 'datetime',
            'is_default' => 'boolean',
            'health_consecutive_failures' => 'integer',
            'health_last_alert_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function healthLogs(): HasMany
    {
        return $this->hasMany(WaHealthLog::class);
    }

    public function isConnected(): bool
    {
        return $this->status === WaStatus::Connected;
    }
}
```

#### 1.5 Modificar `app/Models/Tenant.php`

Agregar relaciones (NO eliminar los campos/accessors `wa_*` existentes):

```php
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;

public function whatsappLines(): HasMany
{
    return $this->hasMany(WhatsAppLine::class);
}

public function defaultWhatsAppLine(): HasOne
{
    return $this->hasOne(WhatsAppLine::class)->where('is_default', true);
}

public function hasAnyConnectedLine(): bool
{
    return $this->whatsappLines()->where('status', WaStatus::Connected)->exists();
}
```

#### 1.6 Modificar `app/Models/Conversation.php`

- Agregar `'whatsapp_line_id'` a `$fillable`.
- Agregar relación:

```php
public function whatsappLine(): BelongsTo
{
    return $this->belongsTo(WhatsAppLine::class);
}
```

#### 1.6.1 Modificar `ConversationResource` (API Resource)

Exponer `whatsapp_line_id` y opcionalmente la relación `whatsapp_line` en el API Resource de conversaciones. Buscar el archivo `app/Http/Resources/ConversationResource.php` (o similar) y agregar:

```php
'whatsapp_line_id' => $this->whatsapp_line_id,
'whatsapp_line'    => $this->whenLoaded('whatsappLine', fn () => [
    'id'     => $this->whatsappLine->id,
    'label'  => $this->whatsappLine->label,
    'phone'  => $this->whatsappLine->phone,
    'status' => $this->whatsappLine->status->value,
]),
```

Asegurar que los controllers que retornan conversaciones hagan `->load('whatsappLine')` o `->with('whatsappLine')` en las queries.

#### 1.7 Modificar `app/Models/WaHealthLog.php`

- Agregar `'whatsapp_line_id'` a `$fillable`.
- Agregar relación:

```php
public function whatsappLine(): BelongsTo
{
    return $this->belongsTo(WhatsAppLine::class);
}
```

---

### Fase 2 — Cambios de ruteo core

#### 2.1 `app/Http/Controllers/WebhookController.php`

Cambiar resolución de tenant. En vez de:
```php
$tenant = Tenant::where('wa_instance_id', $instance)->first();
```

Hacer:
```php
$line = WhatsAppLine::where('instance_id', $instance)->first();
if (! $line) {
    return response()->json(['ignored' => true]);
}
$tenant = $line->tenant;
```

Pasar `$line` a `WebhookHandlerService::handle()`.

#### 2.2 `app/Services/WebhookHandlerService.php`

- Cambiar signature de `handle()` para recibir `WhatsAppLine $line` además del payload.
- El tenant se obtiene de `$line->tenant`.
- `handleConnectionUpdate()`: actualizar `$line->status`, `$line->phone`, `$line->connected_at` (en vez de `$tenant->wa_*`). Seguir actualizando también `$tenant->wa_*` para backward compat durante la transición.
- `handleMessagesUpsert()`: pasar `$line->id` al despachar `HandleInboundMessage`.
- `handleMessagesUpdate()`: el código actual busca `Message::where('tenant_id', $tenant->id)->where('wa_message_id', $waMessageId)`. Con múltiples líneas, los `wa_message_id` de Evolution API podrían colisionar entre instancias. Cambiar la query para scoped por línea:
  ```php
  Message::withoutGlobalScope('tenant')
      ->where('tenant_id', $tenant->id)
      ->where('wa_message_id', $waMessageId)
      ->whereHas('conversation', fn ($q) => $q->where('whatsapp_line_id', $line->id))
      ->update(['status' => $status->value]);
  ```
- `handleQrCodeUpdated()`: actualizar `$line->status` y broadcast con `lineId`.

#### 2.2.1 Refactorizar `app/Events/WaStatusUpdated.php`

El evento actual recibe solo `Tenant` y lee `$tenant->wa_*` para el payload broadcast. Refactorizar para soportar líneas:

```php
class WaStatusUpdated implements ShouldBroadcast
{
    public function __construct(
        public readonly Tenant $tenant,
        public readonly ?WhatsAppLine $line,
        public readonly ?string $qrCode,
    ) {}

    public function broadcastWith(): array
    {
        // Si viene una línea, usar datos de la línea
        if ($this->line) {
            return [
                'line_id'      => $this->line->id,
                'label'        => $this->line->label,
                'status'       => $this->line->status->value,
                'phone'        => $this->line->phone,
                'connected_at' => $this->line->connected_at?->toIso8601String(),
                'qr_code'      => $this->qrCode,
                // Backward compat: mantener campos legacy para frontend viejo
                'legacy'       => true,
            ];
        }

        // Fallback legacy (durante transición)
        return [
            'status'       => $this->tenant->wa_status->value,
            'phone'        => $this->tenant->wa_phone,
            'connected_at' => $this->tenant->wa_connected_at?->toIso8601String(),
            'qr_code'      => $this->qrCode,
        ];
    }
}
```

Todos los lugares que disparan `WaStatusUpdated` deben pasar `$line` como segundo argumento. El canal broadcast sigue siendo `private-tenant.{tenant_id}` (no cambia).

#### 2.3 `app/Jobs/HandleInboundMessage.php`

- Agregar al constructor: `private readonly string $whatsappLineId`.
- En `processMessage()`: pasar `$this->whatsappLineId` a `CreateOrUpdateConversation::handle()`.
- Para media download: cargar la línea y usar `$line->instance_id`.

#### 2.4 `app/Actions/WhatsApp/CreateOrUpdateConversation.php`

Cambio semántico clave: un contacto puede tener conversaciones abiertas en distintas líneas.

- Agregar parámetro `string $whatsappLineId` a `handle()`.
- En la query de búsqueda de conversación existente, agregar: `->where('whatsapp_line_id', $whatsappLineId)`.
- Al crear conversación nueva, setear `'whatsapp_line_id' => $whatsappLineId`.

#### 2.5 `app/Actions/WhatsApp/CreateOrUpdateContact.php`

- En `sanitizeResolvedPhone()`: cambiar `$tenant->wa_phone` por el phone de la línea específica.
- Pasar el phone de la línea como parámetro en vez de leerlo del tenant.

#### 2.6 `app/Jobs/SendWhatsAppMessage.php`

Cambiar resolución de instancia:

```php
// Antes:
$instanceName = WhatsAppClientService::instanceName($tenant->id);

// Después:
$line = $message->conversation->whatsappLine;
if (! $line || ! $line->isConnected()) {
    // marcar mensaje como failed con error_message = 'Line disconnected'
    return;
}
$instanceName = $line->instance_id;
```

Cargar relaciones: `$message->load(['conversation.whatsappLine', 'conversation.contact'])`.

#### 2.7 `app/Jobs/CheckInstanceHealth.php`

Cambiar de iterar tenants a iterar líneas:

```php
// Antes:
Tenant::whereNotNull('wa_instance_id')->each(...)

// Después:
WhatsAppLine::whereNotNull('instance_id')
    ->where('status', '!=', WaStatus::Disconnected)
    ->with('tenant')
    ->each(fn (WhatsAppLine $line) => $this->checkLine($line, $client));
```

Actualizar `$line->health_consecutive_failures` y `$line->health_last_alert_at` (en vez de los campos del tenant).
Crear health logs con `whatsapp_line_id`.

#### 2.8 `app/Services/WhatsAppClientService.php`

Cambiar naming de instancias:

```php
// Nuevo método (para líneas nuevas creadas post-migración):
public static function instanceName(string $lineId): string
{
    return 'line_' . substr(str_replace('-', '', $lineId), 0, 12);
}

// Mantener temporalmente (las instancias migradas conservan su instance_id original):
public static function legacyInstanceName(string $tenantId): string
{
    return 'tenant_' . substr(str_replace('-', '', $tenantId), 0, 8);
}
```

NOTA: Las instancias existentes mantienen su `instance_id` original (ya migrado a `whatsapp_lines.instance_id`). El nuevo `instanceName()` solo se usa para NUEVAS líneas.

---

### Fase 3 — Nuevos endpoints API

#### 3.1 Crear `app/Http/Controllers/Api/V1/WhatsAppLineController.php`

Endpoints:

| Método | Ruta | Acción | Auth |
|--------|------|--------|------|
| GET | `/api/v1/whatsapp/lines` | Listar líneas del tenant | any |
| POST | `/api/v1/whatsapp/lines` | Crear línea (solo label) | owner |
| PATCH | `/api/v1/whatsapp/lines/{line}` | Renombrar / set default | owner |
| DELETE | `/api/v1/whatsapp/lines/{line}` | Soft delete | owner |
| POST | `/api/v1/whatsapp/lines/{line}/connect` | Crear instancia Evolution + QR | owner |
| POST | `/api/v1/whatsapp/lines/{line}/disconnect` | Eliminar instancia Evolution | owner |
| GET | `/api/v1/whatsapp/lines/{line}/status` | Status en vivo vs Evolution | any |
| GET | `/api/v1/whatsapp/lines/{line}/health-logs` | Health logs de la línea | any |

**Lógica de `store()`**:
1. Verificar límite de plan: `$tenant->currentPlan()->maxWhatsAppLines()`. Si ya tiene >= max (y max != -1), retornar 422.
2. Dentro de `DB::transaction`, lockear el row del tenant para serializar acceso:
   ```php
   DB::transaction(function () use ($tenant, $label) {
       // Lock del tenant para evitar race condition con 0 líneas
       Tenant::whereKey($tenant->id)->lockForUpdate()->first();
       $hasLines = $tenant->whatsappLines()->exists();
       return $tenant->whatsappLines()->create([
           'label'      => $label,
           'is_default' => ! $hasLines,
       ]);
   });
   ```
   Esto previene que dos requests concurrentes lean "0 líneas" y ambas creen con `is_default = true`. El partial unique index en DB es la defensa final.

**Lógica de `update()`**:
- Si se setea `is_default = true`, dentro de `DB::transaction` con `lockForUpdate`:
  ```php
  DB::transaction(function () use ($tenant, $line) {
      $tenant->whatsappLines()->lockForUpdate()->get();
      $tenant->whatsappLines()->where('id', '!=', $line->id)->update(['is_default' => false]);
      $line->update(['is_default' => true]);
  });
  ```

**Lógica de `destroy()`**:
- Bloquear si tiene conversaciones abiertas (status open o pending). Retornar 422.
- Si es la línea default y hay otras líneas, bloquear. Retornar 422 con mensaje "Asigna otra línea como default primero".
- Desconectar instancia de Evolution si está conectada.
- Soft delete.

**Lógica de `connect()`**:
- Similar a `WhatsAppController::connect()` actual pero opera sobre la línea.
- Generar `instance_id` con `WhatsAppClientService::instanceName($line->id)`.
- Crear instancia en Evolution, retornar QR.
- Actualizar `$line->instance_id`, `$line->status = qr_pending`.

**Lógica de `disconnect()`**:
- Eliminar instancia de Evolution.
- Limpiar `$line->instance_id`, `$line->status = disconnected`, `$line->phone = null`, `$line->connected_at = null`.

#### 3.2 Agregar rutas en `routes/api.php`

```php
Route::prefix('whatsapp/lines')->group(function () {
    Route::get('/', [WhatsAppLineController::class, 'index']);
    Route::post('/', [WhatsAppLineController::class, 'store']);
    Route::patch('/{line}', [WhatsAppLineController::class, 'update']);
    Route::delete('/{line}', [WhatsAppLineController::class, 'destroy']);
    Route::post('/{line}/connect', [WhatsAppLineController::class, 'connect']);
    Route::post('/{line}/disconnect', [WhatsAppLineController::class, 'disconnect']);
    Route::get('/{line}/status', [WhatsAppLineController::class, 'status']);
    Route::get('/{line}/health-logs', [WhatsAppLineController::class, 'healthLogs']);
});
```

Aplicar el mismo middleware de role/auth que tienen las rutas actuales de WhatsApp. Las rutas de escritura (POST, PATCH, DELETE) deben requerir role owner.

**Tenant-scope en route model binding**: Las rutas con `{line}` deben garantizar que la línea pertenece al tenant autenticado. Usar scoped binding en el route:

```php
Route::prefix('whatsapp/lines')->group(function () {
    // ...rutas con {line} usan scoped binding:
    Route::patch('/{line}', ...)->scopeBindings();
    // etc.
});
```

O validar explícitamente en cada método del controller:
```php
abort_unless($line->tenant_id === auth()->user()->tenant_id, 403);
```

El trait `BelongsToTenant` con global scope ya filtra queries normales, pero el route model binding podría bypassear el scope si usa `findOrFail` directamente. Verificar que el binding pase por el global scope, o agregar el `abort_unless` como defensa en profundidad.

#### 3.3 Backward compatibility en `WhatsAppController`

Mantener los endpoints viejos funcionando delegando a la línea default:

Extraer un helper reutilizable (en `Tenant` o como método privado en un trait/service):

```php
// En Tenant.php o en un service compartido
public function getOrCreateDefaultLine(): WhatsAppLine
{
    return DB::transaction(function () {
        Tenant::whereKey($this->id)->lockForUpdate()->first();

        // 1. Buscar línea default existente
        $default = $this->defaultWhatsAppLine;
        if ($default) {
            return $default;
        }

        // 2. Si hay líneas pero ninguna es default (dato inconsistente), promover la primera
        $first = $this->whatsappLines()->first();
        if ($first) {
            $first->update(['is_default' => true]);
            return $first;
        }

        // 3. No hay líneas: crear la primera
        return $this->whatsappLines()->create([
            'label'      => 'Principal',
            'is_default' => true,
        ]);
    });
}
```

Usar en los endpoints legacy:

```php
// POST /api/v1/whatsapp/connect
public function connect(Request $request): JsonResponse
{
    $tenant = $request->user()->tenant;
    $line = $tenant->getOrCreateDefaultLine();

    return app(WhatsAppLineController::class)->connect($request, $line);
}
```

Hacer lo mismo para `disconnect`, `status`, `health-logs`. El mismo `getOrCreateDefaultLine()` se puede reutilizar en `WhatsAppLineController::store()` para la lógica de primera línea.

#### 3.4 Filtro por línea en `ConversationController::index()`

Agregar query param opcional `whatsapp_line_id`:

```php
$query->when($request->whatsapp_line_id, fn ($q, $lineId) =>
    $q->where('whatsapp_line_id', $lineId)
);
```

#### 3.5 Aceptar `whatsapp_line_id` en creación de conversación

Cambios explícitos:

**`StoreConversationRequest`** (o el FormRequest equivalente para crear conversación outbound):
```php
'whatsapp_line_id' => ['nullable', 'uuid', Rule::exists('whatsapp_lines', 'id')
    ->where('tenant_id', $this->user()->tenant_id)
    ->whereNull('deleted_at')],
```

**Controller** (donde se crea la conversación):
```php
$lineId = $request->whatsapp_line_id ?? $tenant->defaultWhatsAppLine?->id;
abort_unless($lineId, 422, 'No WhatsApp line available.');

$line = WhatsAppLine::findOrFail($lineId);
abort_unless($line->isConnected(), 422, 'Line is not connected.');

// Pasar lineId al action:
$result = $action->handle($request->user(), $request->validated(), $lineId);
```

**`CreateConversation` action** (o equivalente para outbound):
- Aceptar `string $whatsappLineId` como parámetro.
- Buscar conversación abierta existente con scope: `->where('contact_id', $contact->id)->where('whatsapp_line_id', $whatsappLineId)`.
- Si crea nueva, setear `'whatsapp_line_id' => $whatsappLineId`.

#### 3.6 Límite por plan

Agregar método a `app/Enums/TenantPlan.php` (que ya tiene `maxAgents()`, `maxContacts()`, `maxAutomations()` con el mismo patrón):

```php
/**
 * Max WhatsApp lines allowed (-1 = unlimited).
 */
public function maxWhatsAppLines(): int
{
    return match ($this) {
        self::Trial => 1,
        self::Seed  => 1,
        self::Grow  => 3,
        self::Scale => -1,
    };
}
```

Uso en el controller: `$tenant->currentPlan()->maxWhatsAppLines()` (NO `$tenant->plan->...`).

---

## Edge cases y reglas de negocio

| Caso | Comportamiento esperado |
|------|------------------------|
| Borrar última línea con conversaciones abiertas | Bloquear con error 422. |
| Un contacto escribe a 2 líneas distintas | Se crean 2 conversaciones separadas (una por línea). El contacto es el mismo registro. |
| Outbound sin `whatsapp_line_id` | Usar `tenant.defaultWhatsAppLine`. Si no hay default, error 422. |
| Línea se desconecta con conversaciones abiertas | Mensajes salientes fallan con status `failed` y `error_message = 'Line disconnected'`. |
| Downgrade de plan (3 → 1 línea) | No desconectar líneas existentes. Bloquear creación de nuevas. |
| Webhook de instancia desconocida | Ya manejado: retorna 200 con `{ ignored: true }`. |
| Set default en línea X | En transacción: `update all is_default=false`, luego `update target is_default=true`. |

---

## Orden de implementación

1. Migraciones (1.1, 1.2, 1.3) — crear tabla, agregar FK, migrar datos
2. Modelo WhatsAppLine (1.4)
3. Relaciones en Tenant, Conversation, WaHealthLog (1.5, 1.6, 1.7)
4. WebhookController + WebhookHandlerService (2.1, 2.2)
5. HandleInboundMessage (2.3)
6. CreateOrUpdateConversation (2.4)
7. CreateOrUpdateContact (2.5)
8. SendWhatsAppMessage (2.6)
9. CheckInstanceHealth (2.7)
10. WhatsAppClientService (2.8)
11. WhatsAppLineController + rutas (3.1, 3.2)
12. Backward compat en WhatsAppController (3.3)
13. Filtro en ConversationController (3.4)
14. whatsapp_line_id en creación de conversación (3.5)
15. Límite por plan (3.6)

---

## Tests requeridos

- **Unit**: `WhatsAppLine` model — casts, relaciones, `isConnected()`.
- **Unit**: `Tenant` — `defaultWhatsAppLine()`, `hasAnyConnectedLine()`.
- **Feature**: Migración de datos — verificar que líneas se crean y conversations se linkean.
- **Feature**: Webhook resolution — webhook con instance_id de línea resuelve correctamente.
- **Feature**: HandleInboundMessage — conversación creada tiene `whatsapp_line_id`.
- **Feature**: SendWhatsAppMessage — usa instancia de la línea, no del tenant.
- **Feature**: WhatsAppLineController CRUD — crear, renombrar, set default, eliminar, connect, disconnect.
- **Feature**: Límite de plan — no se pueden crear más líneas del permitido.
- **Feature**: ConversationController index con filtro `whatsapp_line_id`.
- **Feature**: Backward compat — endpoints legacy delegan a línea default.

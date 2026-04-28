# AMIA — Plan Fase 1: tool-calling + agenda real sobre `reservations`

Audiencia: agente ejecutor (Codex). Self-contained. Lee este archivo, ejecuta paso a paso, reporta bloqueos. **No avances al siguiente paso si el actual no cumple su criterio de aceptación.**

## Contexto

- AriCRM es un SaaS multi-tenant Laravel 12 + React (Inertia) + PostgreSQL. Stack y convenciones en `CLAUDE.md`.
- Tenant AMIA (`tenant_id = 019d92aa-9b2a-72a3-ad07-d59168920642`, vertical `health`) tiene un `AiAgent` activo respondiendo a WhatsApp con un prompt que **referencia 6 herramientas** (`get_contact`, `get_available_slots`, `create_appointment`, `update_contact`, `handoff_to_human`, `schedule_follow_up`). Hoy esas herramientas **no existen** en el código — el agente las alucina.
- Fase 0 (ya aplicada en prod) prepended un override al `system_prompt` para que el agente trabaje en modo conversacional puro sin proponer horarios. Cuando esta Fase 1 esté lista, ese override se retira.
- Existe ya un sistema de `reservations` con `BuildReservationSlots`, estados, API REST y UI en `/reservations`. **Reusarlo** — no crear paralelo.
- Prompt del agente: `docs/amia/prompt.md`.

## Decisiones de diseño (no las cuestiones a media implementación)

1. **Modelo de recurso reservado: tabla nueva `bookable_units`** (no shadow Users, no string libre). Type-agnóstico: profesionales, mesas, salas, etc. `reservations.bookable_unit_id` nullable para no romper reservas existentes.
2. **Servicio**: nueva columna `service` (string) en `reservations`. Mapa de duraciones por servicio en `config/amia.php`.
3. **`assigned_to` mantiene su semántica actual**: staff responsable (asistente humana), no recurso reservado.
4. **Tool calling**: sólo Anthropic (Claude) en esta fase. OpenAI/Gemini **no usan tool calling**; si `useTools=true` y el provider resuelto no es Anthropic, se lanza excepción y la conversación queda sin respuesta IA (la atiende el equipo humano). Sin degradación silenciosa.
5. **Auto-confirm**: el AI crea reservaciones en estado `requested`. Una asistente humana las pasa a `confirmed` desde la UI. Esto preserva un humano en el loop hasta que validemos calidad.
6. **Multi-step tool loop**: máximo 5 iteraciones por turno antes de cortar y forzar respuesta de texto.

## Preguntas abiertas a resolver antes del paso correspondiente

| # | Pregunta | Bloquea paso | Default si no hay respuesta |
|---|---|---|---|
| Q1 | ¿Cuántos profesionales tiene AMIA y qué servicios presta cada uno? | Paso 8 (seed) | Crear 1 unidad placeholder "Profesional AMIA" y completar luego. |
| Q2 | ¿Profesionales atienden todo el horario del centro o tienen horario propio? | Paso 2 (schema) | Asumir horario del tenant; `bookable_units.settings.working_hours` queda como override opcional. |
| Q3 | ¿Existe línea WhatsApp de pruebas para AMIA o se prueba directo en una de las dos prod? | Paso 11 (rollout) | Crear una línea nueva para tests; no activar en líneas con tráfico real hasta validar. |

**Q4 ya resuelta**: `app/Models/Task.php` existe con `tenant_id, user_id, assigned_to, contact_id, conversation_id, deal_id, title, description, due_at, reminded_at, completed_at, priority` (enum). Cubre el caso. `schedule_follow_up` crea una `Task` ligada a la `Conversation`. No agregar columnas a `conversations`.

Cuando un paso dependa de una pregunta abierta, **detente y pregunta antes de seguir** salvo que el default sea aceptable y esté escrito arriba.

---

## Paso 0 — Preparación

- Crear rama `feature/amia-tool-calling` desde `main`.
- Leer `docs/amia/prompt.md` completo para entender el comportamiento esperado.
- Leer estos archivos (no editar todavía):
  - `app/Services/AiAgentService.php`
  - `app/Http/Controllers/Api/V1/AiAgentController.php`
  - `app/Models/AiAgent.php`
  - `database/migrations/2026_04_03_130000_create_reservations_table.php`
  - `app/Models/Reservation.php`
  - `app/Enums/ReservationStatus.php`
  - `app/Actions/Reservations/BuildReservationSlots.php`
  - `app/Http/Controllers/Api/V1/ReservationController.php`
  - `app/Models/Contact.php`, `app/Models/Conversation.php`, `app/Models/User.php`, `app/Models/Tenant.php`

**Aceptación:** rama creada, lectura completa, sin cambios todavía.

---

## Paso 1 — Migración: `bookable_units`

Crear migración `create_bookable_units_table`:

```php
Schema::create('bookable_units', function (Blueprint $t) {
    $t->uuid('id')->primary();
    $t->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
    $t->string('type', 40);                       // 'professional','table','room','equipment'
    $t->string('name');                           // "Dra. Jurado", "Mesa 3"
    $t->unsignedInteger('capacity')->default(1);
    $t->foreignUuid('user_id')->nullable()->constrained()->nullOnDelete();
    $t->json('settings')->nullable();             // {services: [...], working_hours: {...}, section, color}
    $t->boolean('is_active')->default(true);
    $t->softDeletes();
    $t->timestamps();
    $t->index(['tenant_id', 'type', 'is_active']);
});
```

**Aceptación:** `php artisan migrate` corre limpio en local. Rollback funciona.

---

## Paso 2 — Migración: extender `reservations` + propagar a request/resource/UI

Crear migración `add_bookable_unit_and_service_to_reservations`:

```php
Schema::table('reservations', function (Blueprint $t) {
    $t->foreignUuid('bookable_unit_id')->nullable()->after('assigned_to')
        ->constrained('bookable_units')->nullOnDelete();
    $t->string('service', 80)->nullable()->after('bookable_unit_id');
    $t->index(['tenant_id', 'bookable_unit_id', 'starts_at']);
    $t->index(['tenant_id', 'service']);
});
```

**No basta con la migración.** Si solo se agregan las columnas, los nuevos campos quedan invisibles a `/reservations` (porque `ReservationController::store/update` y el resource no los consideran). Hay que tocar también:

1. **`app/Http/Requests/Api/ReservationRequest.php`**: agregar reglas de validación para `bookable_unit_id` (nullable uuid + exists scoped al tenant) y `service` (nullable string in:[lista del catálogo de `config/amia.php`] cuando `vertical=health`; libre en otros verticales).
2. **`app/Http/Controllers/Api/V1/ReservationController.php`**:
   - `store()` (~línea 109): persistir `bookable_unit_id` y `service`.
   - `update()` (~línea 138): mismo.
3. **`app/Http/Resources/ReservationResource.php`**: exponer `bookable_unit` (con id y name del recurso) y `service` en la respuesta.
4. **`resources/js/Pages/Reservations/Index.tsx`** y components ligados:
   - Mostrar columna "Recurso" cuando hay unidades configuradas para el tenant.
   - En el form de crear/editar reserva, picker de `bookable_unit_id` (cargado desde `GET /api/v1/bookable-units?type=professional&active=1`, definido en el Paso 3) y picker de `service` (cuando vertical=health).
   - Si el tenant no tiene unidades, ocultar columna y picker — mantiene comportamiento actual.
5. **`tests/Feature/ReservationApiTest.php`**: agregar casos para create/update con `bookable_unit_id` y `service`, y verificar que el resource los devuelve.

**Aceptación:** migración corre y revierte limpio; tests actualizados pasan; UI de `/reservations` muestra el recurso cuando aplica y permite seleccionarlo al crear; `Reservation::create([..., 'bookable_unit_id' => ..., 'service' => ...])` desde `tinker` se persiste y aparece en la lista.

---

## Paso 3 — Modelo `BookableUnit` + API CRUD

### Modelo

`app/Models/BookableUnit.php`:

- `use BelongsToTenant, HasUuids, SoftDeletes`.
- `$fillable`: `tenant_id, type, name, capacity, user_id, settings, is_active`.
- Casts: `settings => 'array'`, `is_active => 'boolean'`, `capacity => 'integer'`.
- Relations: `tenant()`, `user()` (BelongsTo), `reservations()` (HasMany).
- Scope local `scopeActive($q)`, `scopeOfType($q, string $type)`.

Actualizar `app/Models/Reservation.php`:

- Agregar `bookable_unit_id` y `service` a `$fillable`.
- Relación `bookableUnit(): BelongsTo`.

### API REST mínima

Crear:

- `app/Http/Resources/BookableUnitResource.php`: expone `id, type, name, capacity, user_id, settings, is_active`.
- `app/Http/Requests/Api/BookableUnitRequest.php`: validación de campos al crear/editar (incluye validación del shape de `settings.working_hours` si viene, mismo schema que `tenant.business_hours`).
- `app/Http/Controllers/Api/V1/BookableUnitController.php` con `index, store, show, update, destroy`.
- Registrar rutas en `routes/api.php` dentro del grupo tenant-scoped, **mismo nivel y middleware que `reservations`**:

```php
Route::get('/bookable-units',           [BookableUnitController::class, 'index']);
Route::post('/bookable-units',          [BookableUnitController::class, 'store'])->middleware('role:admin');
Route::get('/bookable-units/{unit}',    [BookableUnitController::class, 'show']);
Route::put('/bookable-units/{unit}',    [BookableUnitController::class, 'update'])->middleware('role:admin');
Route::delete('/bookable-units/{unit}', [BookableUnitController::class, 'destroy'])->middleware('role:admin');
```

`index` soporta filtros `?type=professional&service=citologia&active=1` para que tanto la UI de reservations como las herramientas del agente IA puedan listar profesionales por servicio.

### UI mínima de admin

`resources/js/Pages/BookableUnits/Index.tsx`: tabla simple con CRUD. No es prioridad estética — es para que un admin pueda crear unidades sin tinker. Acceso desde el menú lateral si el usuario tiene rol admin.

### Tests

- `tests/Feature/BookableUnitApiTest.php`: cubre CRUD, scoping por tenant, filtros del index.

**Aceptación:**
- `BookableUnit::create([...])` funciona en `tinker` y `$reservation->bookableUnit` carga la relación.
- `GET /api/v1/bookable-units?type=professional&service=citologia` devuelve sólo unidades del tenant del request, activas, cuyo `settings.services[]` incluya `citologia`.
- Page `/bookable-units` lista, crea, edita y desactiva unidades.

---

## Paso 4 — Refactor `BuildReservationSlots`

Cambiar firma:

```php
public function handle(
    Tenant $tenant,
    CarbonImmutable $baseDate,
    int $days = 7,
    int $durationMinutes = 60,
    int $stepMinutes = 30,
    ?string $bookableUnitId = null,
    ?string $jornada = null,        // 'morning' | 'afternoon' | null
): array
```

Lógica:

- Si `$bookableUnitId` viene, cargar la unidad y usar `settings.working_hours` si existe y es válido (mismo schema que `tenant.business_hours`, ver abajo); si no, `tenant.business_hours`.
- `isOccupied()` filtra `bookable_unit_id = $bookableUnitId` cuando se pasa; sin él mantiene comportamiento actual (cualquier reservación cuenta como ocupada).
- Si `$jornada === 'morning'`, descartar slots con hora ≥ 12:00 local.
- Si `$jornada === 'afternoon'`, descartar slots con hora < 12:00 local.

### Schema obligatorio de `bookable_units.settings.working_hours`

**Debe ser idéntico al de `tenant.business_hours`** que ya existe en producción (ejemplo real del tenant AMIA, `019d92aa...`):

```jsonc
{
  "monday":    { "enabled": true,  "blocks": [{"start":"08:00","end":"12:00"},{"start":"14:00","end":"18:00"}] },
  "tuesday":   { "enabled": true,  "blocks": [{"start":"08:00","end":"12:00"},{"start":"14:00","end":"18:00"}] },
  "wednesday": { "enabled": true,  "blocks": [{"start":"08:00","end":"12:00"},{"start":"14:00","end":"18:00"}] },
  "thursday":  { "enabled": true,  "blocks": [{"start":"08:00","end":"12:00"},{"start":"14:00","end":"18:00"}] },
  "friday":    { "enabled": true,  "blocks": [{"start":"08:00","end":"12:00"},{"start":"14:00","end":"18:00"}] },
  "saturday":  { "enabled": true,  "blocks": [{"start":"08:00","end":"12:00"}] },
  "sunday":    { "enabled": false, "blocks": [{"start":"09:00","end":"18:00"}] }
}
```

Notar: `tenant.business_hours` usa **`blocks[]` (multi-rango por día)**, no un par `start/end` plano. El código actual de `BuildReservationSlots::resolveWindow()` (líneas 70-84) sólo lee un `start/end` plano y por tanto **ya tiene un bug latente** vs el shape real de prod. El refactor debe:

1. Recorrer `dayConf.blocks[]` y generar slots por cada bloque (no un único rango por día).
2. Mantener compat con el shape antiguo `{start, end}` plano por si algún tenant lo tiene; convertir en runtime a `blocks: [{start, end}]`.
3. La unidad sigue exactamente el mismo contrato — sin nuevos campos.

**No cambiar la API `GET /api/v1/reservations/slots` todavía**; el agente AI usará la action directamente.

**Aceptación:** test unitario `tests/Unit/BuildReservationSlotsTest.php` que cubre:

1. Sin `bookableUnitId` → comportamiento actual (regresión).
2. Con `bookableUnitId` y reserva existente de OTRA unidad → slot disponible.
3. Con `bookableUnitId` y reserva existente de la MISMA unidad → slot ocupado.
4. `jornada=morning` filtra correctamente.
5. `business_hours` con multi-bloque (8-12 / 14-18) genera slots en ambos rangos y NUNCA entre 12-14.
6. `bookable_units.settings.working_hours` (mismo schema multi-bloque) sobreescribe `tenant.business_hours`.

---

## Paso 5 — Config: duraciones por servicio

`config/amia.php`:

```php
return [
    'service_durations' => [
        'consulta_ginecologica' => 60,
        'medicina_funcional' => 90,
        'ginecologia_estetica' => 60,
        'fisioterapia_piso_pelvico' => 60,
        'cirugia_minimamente_invasiva' => 90,
        'spa' => 60,
        'farmacia' => 30,
        'psicologia' => 60,
        'control_ginecologico' => 30,
        'citologia' => 30,
        'colposcopia' => 45,
    ],
    'default_duration' => 60,
];
```

Helper en código (no helper global): `App\Support\AmiaServiceCatalog::durationFor(string $service): int` que lee `config('amia.service_durations.'.$service, config('amia.default_duration'))`.

**Aceptación:** `AmiaServiceCatalog::durationFor('citologia') === 30` en test unitario.

---

## Paso 6 — Tool calling en `AiAgentService` (Anthropic)

### Regla de oro: extender, no duplicar

`app/Services/AiAgentService.php` ya hace **todo** el trabajo de provider lookup, fallback chain, API key resolution, logging contextual y excepciones tipadas (`AiProviderException`). El método público actual `generateReplyWithMeta(...)` retorna `['reply', 'provider', 'model']` y soporta `disableFallback`.

**Tool calling se integra dentro de `generateAnthropicReply` y/o como un wrapper que comparte la misma resolución de provider.** Está prohibido:

- Crear un nuevo `HttpClient` con base url, headers, timeouts y manejo de errores propios.
- Re-implementar la cadena de fallback de providers.
- Agregar excepciones nuevas; reutilizar `AiProviderException`.
- Re-implementar `logContext(...)` o el formato de logs.

### Interface y registry

`app/Services/AiAgent/Tools/Tool.php`:

```php
interface Tool
{
    public function name(): string;
    public function description(): string;
    /** @return array<string, mixed> JSONSchema según Anthropic tools spec */
    public function inputSchema(): array;
    /** @param array<string, mixed> $input @return array<string, mixed> serializable a JSON */
    public function execute(Conversation $conversation, array $input): array;
}
```

`app/Services/AiAgent/ToolRegistry.php`:

```php
class ToolRegistry {
    /** @param iterable<Tool> $tools */
    public function __construct(iterable $tools) { /* index by name */ }
    /** @return array<int, array{name:string,description:string,input_schema:array}> */
    public function definitionsForAnthropic(): array;
    public function get(string $name): ?Tool;
}
```

**Binding explícito en `AppServiceProvider::register()`** (Laravel no auto-inyecta arrays de interfaces):

```php
$this->app->singleton(ToolRegistry::class, function ($app) {
    return new ToolRegistry([
        $app->make(Tools\GetContactTool::class),
        $app->make(Tools\UpdateContactTool::class),
        $app->make(Tools\GetAvailableSlotsTool::class),
        $app->make(Tools\CreateAppointmentTool::class),
        $app->make(Tools\HandoffToHumanTool::class),
        $app->make(Tools\ScheduleFollowUpTool::class),
    ]);
});
```

Cada Tool puede tener sus propias dependencies (Action, repository) inyectadas por el container.

### Cambio en `AiAgentService` (cirugía mínima)

1. **Agregar dos parámetros nuevos al FINAL de la firma** de `generateReplyWithMeta(...)` para no romper callers existentes que usan named args (`disableFallback:`, `context:` ya están en uso desde el playground):

```php
public function generateReplyWithMeta(
    AiAgent $agent,
    string $prompt,
    array $history,
    bool $disableFallback = false,
    array $context = [],
    bool $useTools = false,                 // NUEVO
    ?Conversation $conversation = null,     // NUEVO
): ?array
```

Cuando `$useTools === true && $conversation !== null`, activa el flujo con tools. En cualquier otro caso (incluido el playground), comportamiento idéntico al actual. Si `$useTools=true` y `$conversation=null`, lanzar `\InvalidArgumentException` — es un bug del caller, no se silencia.

2. **Sólo Anthropic soporta tools** en esta fase. Si `$useTools=true`:
   - El provider primario debe resolver a `anthropic`. Si el `llm_model` configurado en el `AiAgent` no es Claude, **lanzar `AiProviderException`** con `provider='anthropic'`, `status=0` y mensaje `"Tool calling requires Anthropic provider; current model: {x}"`. **No degradar a texto puro** — un provider sin tools podría inventar confirmaciones de citas o follow-ups.
   - **La cadena de fallback se desactiva implícitamente**: cuando `$useTools=true`, sólo se intenta el provider Anthropic. Si Anthropic está caído o sin clave, la llamada falla y el caller (`generateAndSend`) debe **dejar la conversación silenciosa** (no enviar mensaje IA) y dejar que el equipo humano la atienda. Loggear `Log::error('AiAgent: tool calling unavailable, skipping IA reply', ...)`.
   - Equivalentemente: con `useTools=true` se usa **siempre Claude o nada**.

3. **Nuevo método privado** `generateAnthropicReplyWithTools(string $apiKey, string $model, string $prompt, array $history, Conversation $conv, string $agentId, array $context): array` que retorna **el mismo shape que `generateAnthropicReply` (string|null)** o lanza `AiProviderException` para que la cadena de fallback en `generateReplyWithMeta` lo maneje sin cambios.

4. **`generateAnthropicReply` se mantiene como está** (lo usa el playground, que NO necesita tools).

### Protocolo Anthropic tool_use / tool_result (correcto)

El plan anterior decía "anexar tool_result al history" — eso **no es suficiente y rompe el formato de Anthropic**. La forma correcta:

1. Request inicial:

```json
{
  "model": "claude-haiku-4-5",
  "max_tokens": 1024,
  "system": "<prompt>",
  "tools": [<defs del registry>],
  "messages": [
    {"role": "user", "content": "Hola, quiero citologia"}
  ]
}
```

2. La respuesta puede tener `stop_reason: "tool_use"` con `content[]` que contiene **bloques mixtos**, ej:

```json
{
  "stop_reason": "tool_use",
  "content": [
    {"type": "text", "text": "Reviso disponibilidad."},
    {"type": "tool_use", "id": "toolu_01ABC", "name": "get_available_slots", "input": {"service": "citologia"}}
  ]
}
```

3. **Para el siguiente request hay que preservar EL MENSAJE COMPLETO del assistant tal como vino** (incluido el bloque `tool_use` con su `id`) y agregar **un mensaje del user con bloques `tool_result`** correlacionados por `tool_use_id`:

```json
"messages": [
  {"role": "user",      "content": "Hola, quiero citologia"},
  {"role": "assistant", "content": [
      {"type": "text", "text": "Reviso disponibilidad."},
      {"type": "tool_use", "id": "toolu_01ABC", "name": "get_available_slots", "input": {"service": "citologia"}}
  ]},
  {"role": "user", "content": [
      {"type": "tool_result", "tool_use_id": "toolu_01ABC",
       "content": "{\"slots\":[{...},{...}]}"}
  ]}
]
```

**Importante:**

- El bloque `assistant` NO debe ser modificado ni "aplanado" a string. Pasarlo tal cual al siguiente turno.
- Si el modelo invoca múltiples tools en un solo turno, devolver **un solo mensaje user con un `tool_result` por cada `tool_use_id`** en el mismo orden que `content[]` lo permite (el orden no importa en Anthropic, pero los IDs sí).
- `tool_result.content` debe ser string (típicamente JSON-encoded `execute()` output) o array de bloques `text`/`image`. En Fase 1 todo es string JSON.
- Si una tool lanza excepción, el `tool_result` debe incluir `"is_error": true` y un mensaje legible.
- El history pre-existente (que viene del CRM como `[{role,content:string}]`) sólo necesita transformación cuando una assistant turn previa contiene tool_use; en el primer turno de la conversación AI eso no pasa, así que en Fase 1 podemos asumir que el history entrante es plano y la complejidad de "preservar bloques previos" sólo aplica entre llamadas dentro del mismo turno.

### Loop

```text
messages = transform(history) + [{role:user, content: latest_user_message}]
for i in 0..maxIterations:
    response = anthropic.messages.create({system, tools, messages, model, max_tokens})
    append response.content (as the assistant message) to messages
    if response.stop_reason == "end_turn":
        return concatenated text blocks from response.content
    if response.stop_reason == "tool_use":
        tool_results = []
        for block in response.content where type=="tool_use":
            try:
                output = registry.get(block.name).execute(conversation, block.input)
                tool_results.append({type:"tool_result", tool_use_id:block.id, content:json_encode(output)})
            except Throwable e:
                tool_results.append({type:"tool_result", tool_use_id:block.id, is_error:true,
                                     content:"Tool failed: "+e.message})
        messages.append({role:"user", content: tool_results})
        continue
    # max_tokens, stop_sequence, etc.
    return concatenated text blocks (best-effort)
return null  # loop exhausted; log warning
```

### Restricciones operativas

- `maxIterations = 5` por defecto.
- Cada `Tool::execute` corre dentro de un `try/catch` y un timeout PHP suave (limit time del request handler ya aplica; no hace falta micro-managing salvo si la tool hace HTTP externo, en cuyo caso la propia tool pone su timeout).
- Logging: cada invocación → `Log::info('AiAgent tool', logContext('anthropic', $agentId, null, ['conversation_id' => ..., 'tool' => $name, 'iteration' => $i, 'input_keys' => array_keys($input), 'ok' => true|false]))`. Reutilizar el helper `logContext` existente del servicio.

**Aceptación:**
- Test feature `tests/Feature/AiAgentToolCallingTest.php` con `Http::fake()` que simula:
  1. Una secuencia: respuesta `tool_use` → ejecuta tool real → segunda respuesta `end_turn` con texto. Verifica que el segundo request a Anthropic incluye el assistant message con `tool_use` (preservado tal cual, no aplanado a string) y un user message con `tool_result` que tiene el `tool_use_id` correcto.
  2. Tool que lanza excepción → `tool_result.is_error=true` se envía y el modelo recibe el error.
  3. Provider no-anthropic configurado con `useTools=true` → lanza `AiProviderException` (no degrada).
  4. Anthropic API key ausente con `useTools=true` → `generateAndSend` capta la excepción, NO envía mensaje IA y loggea error (la conversación queda para el equipo humano).
  5. `maxIterations` excedido → log warning + retorna último texto disponible (o null).
- `generateReplyWithMeta` sin `useTools` sigue idéntica a antes (no regresión en el playground existente: `tests/Feature/AiAgentPlaygroundTest.php` debe seguir pasando sin cambios).

---

## Paso 7 — Implementar las 6 herramientas

Cada tool en `app/Services/AiAgent/Tools/`. Todas reciben `Conversation` y validan `tenant_id` para impedir cross-tenant. Cada `execute()` retorna un array JSON-serializable.

### Pre-requisito: extraer `GenerateReservationCode` action

`ReservationController::generateReservationCode()` (`app/Http/Controllers/Api/V1/ReservationController.php:174`) es **`private`** y `CreateAppointmentTool` no puede llamarlo. Extraer a `app/Actions/Reservations/GenerateReservationCode.php`:

```php
final class GenerateReservationCode
{
    public function handle(): string
    {
        do {
            $code = 'RES-'.strtoupper(Str::random(6));
        } while (Reservation::query()->where('code', $code)->exists());
        return $code;
    }
}
```

Refactorar `ReservationController::store()` (línea 109) para usar la action vía DI. Sin cambios de comportamiento.

### `GetContactTool`
- Input: `{}` (usa la conversation).
- Output: `{id, name, phone, notes, is_returning, last_visit_at?}`. `is_returning` = true si el contacto tiene reservas previas en estado `seated|completed`.

### `UpdateContactTool`
- Input: `{name?: string, notes?: string, service_of_interest?: string}`.
- Actualiza `contacts.name` y/o `contacts.notes` (concatenando, no sobreescribiendo). `service_of_interest` se guarda en `contacts.notes` con un prefijo `[interés: <servicio>]` para evitar agregar columnas nuevas. Si más adelante se decide normalizar, ese cambio es independiente.
- Output: `{ok: true}`.

### `GetAvailableSlotsTool`
- Input: `{service: string, jornada?: 'morning'|'afternoon', day?: 'YYYY-MM-DD', professional_unit_id?: string}`.
- Resolución de unidad:
  - Si `professional_unit_id` viene, usarla (validar que pertenezca al tenant y `is_active`).
  - Si no, listar `bookable_units` del tenant activas de tipo `professional` cuyo `settings.services` (array) incluya `service`. Iterar y devolver los slots de la primera unidad que tenga al menos uno; si ninguna tiene slots, retornar `{slots: []}`.
- Calcula `duration` con `AmiaServiceCatalog::durationFor($service)`.
- Llama `BuildReservationSlots->handle($tenant, $baseDate, $days, $duration, 30, $unitId, $jornada)` donde `baseDate` = parse(`day`) si viene, si no `now()->startOfDay()`; `days = 3` si `day` viene, `7` si no.
- Devuelve `slots[0..1]` (máximo 2, según el prompt).
- Output: `{slots: [{starts_at, ends_at, label, professional_name, bookable_unit_id}]}`.

### `CreateAppointmentTool`
- Input: `{starts_at: ISO8601, ends_at: ISO8601, service: string, bookable_unit_id: string, contact_name?: string, contact_document?: string, notes?: string}`.
- **Concurrencia**: dos mensajes simultáneos pueden crear citas duplicadas. **`SELECT ... FOR UPDATE` no protege contra inserts en un slot vacío** (no hay filas que bloquear). Hay dos defensas y se aplican AMBAS en Fase 1:

  **Defensa A — `pg_advisory_xact_lock` por slot** (lock dentro de la transacción):

  ```php
  return DB::transaction(function () use ($conv, $input) {
      // Lock keyed by tenant_id + bookable_unit_id + starts_at; se libera al cerrar la transacción.
      $lockKey = sprintf('%s|%s|%s',
          $conv->tenant_id,
          $input['bookable_unit_id'],
          $input['starts_at']
      );
      // pg_advisory_xact_lock toma un bigint; usar hashtext del string.
      DB::statement('SELECT pg_advisory_xact_lock(hashtext(?))', [$lockKey]);

      // Re-chequear ocupación; ahora cualquier transacción concurrente con el mismo key espera.
      $occupied = Reservation::query()
          ->where('tenant_id', $conv->tenant_id)
          ->where('bookable_unit_id', $input['bookable_unit_id'])
          ->whereNotIn('status', [ReservationStatus::Cancelled->value, ReservationStatus::NoShow->value])
          ->where('starts_at', '<', $input['ends_at'])
          ->where('ends_at',   '>', $input['starts_at'])
          ->exists();
      if ($occupied) {
          return ['error' => 'slot_taken', 'message' => 'Ese horario ya no esta disponible'];
      }

      $reservation = Reservation::create([...]);
      return [...];
  });
  ```

  **Defensa B — `EXCLUDE` constraint en Postgres** (red de seguridad si el lock falla o se evade):

  En la migración del Paso 2 (o una migración nueva si conviene aislarla), agregar:

  ```sql
  ALTER TABLE reservations
    ADD CONSTRAINT reservations_no_overlap
    EXCLUDE USING gist (
      tenant_id WITH =,
      bookable_unit_id WITH =,
      tstzrange(starts_at, ends_at, '[)') WITH &&
    )
    WHERE (
      bookable_unit_id IS NOT NULL
      AND deleted_at IS NULL
      AND status NOT IN ('cancelled','no_show')
    );
  ```

  Requiere extensión `btree_gist` (`CREATE EXTENSION IF NOT EXISTS btree_gist;` en la misma migración). El predicado `WHERE` excluye reservas suaves canceladas/no-show y reservas sin unidad asignada (las reservas históricas de restaurante sin `bookable_unit_id`). Si la inserción viola el constraint, capturar la `QueryException` con `getCode() === '23P01'` (exclusion violation) en `CreateAppointmentTool::execute` y devolver `{error: 'slot_taken', ...}`.

  La defensa A previene el caso común; la B es invariante a nivel de DB que ningún path (ni tool, ni controller, ni seeder) puede violar.

- Si `contact_name` viene y el contacto no tiene nombre, actualizar dentro de la misma transacción.
- Crea `Reservation` con: `status=requested`, `tenant_id`, `contact_id` (de la conversation), `conversation_id`, `bookable_unit_id`, `service`, `starts_at`, `ends_at`, `party_size=1`, `notes`, `requested_at=now()`, `code` vía `GenerateReservationCode`.
- **No** disparar broadcasts ni eventos en esta fase salvo que ya exista uno con el mismo nombre — agregar `// TODO emit event` si no.
- Output: `{reservation_id, code, starts_at, professional_name, status: 'requested'}`.

### `HandoffToHumanTool`
- Input: `{reason: string, urgency?: 'normal'|'high'}`.
- Setea `conversation.ai_agent_enabled = false` y persiste.
- Para `urgency === 'high'`: si `conversations` tiene una columna o relación `tags`, agregar tag `urgent`; si no existe esa estructura, anteponer `[URGENTE]` al `notes` o equivalente. Verificar el modelo `Conversation` antes de implementar y elegir lo más limpio sin agregar tablas.
- Disparar `broadcast(new ConversationUpdated($conversation->fresh(...)))` (el evento ya existe y se usa en `AiAgentService`).
- Output: `{ok: true, ai_disabled: true}`.

### `ScheduleFollowUpTool`
- Input: `{at: ISO8601 | 'in_24h' | 'tomorrow_morning', topic: string}`.
- Resolver `at` a `Carbon`:
  - `'in_24h'` → `now()->addDay()`.
  - `'tomorrow_morning'` → `now()->addDay()->setTime(9, 0)` en timezone del tenant.
  - ISO8601 → parse directo en timezone del tenant.
- Crear un `Task` (modelo `app/Models/Task.php` ya existe):

```php
Task::create([
    'tenant_id'       => $conv->tenant_id,
    'user_id'         => null,             // creado por el agente IA, no un user humano
    'assigned_to'     => $conv->assigned_to ?? null,
    'contact_id'      => $conv->contact_id,
    'conversation_id' => $conv->id,
    'deal_id'         => null,
    'title'           => 'Seguimiento: ' . Str::limit($input['topic'], 60),
    'description'     => $input['topic'],
    'due_at'          => $resolvedAt,
    'priority'        => TaskPriority::Normal,    // o el default que tenga el enum
]);
```

- Output: `{ok: true, task_id, scheduled_at}`.

**Aceptación por tool:** test unitario que cubre golden path + error path. Para `CreateAppointmentTool` específicamente, un test que dispare 2 creates concurrentes (vía `DB::transaction` simulado) y verifique que sólo uno gana y el otro recibe `slot_taken`. No hace falta integración E2E aquí — eso va en Paso 10.

---

## Paso 8 — Seed de unidades para AMIA

**Bloqueado por Q1.** Cuando Q1 esté respondida, crear seeder `database/seeders/AmiaBookableUnitsSeeder.php` con estas reglas **idempotentes y no destructivas**:

1. **Nunca borrar unidades existentes**. Pueden tener `reservations` ligadas históricas; eliminarlas las orphana o rompe FKs.
2. **Definir cada unidad con un `slug` estable** (ej: `dra-jurado`, `dra-x`) que NO viva en columna sino en `settings.slug`. Usar el slug como clave de upsert.
3. Para cada profesional definida en el seeder:
   - Buscar unidad existente por `(tenant_id, settings->slug)`. Si existe → **actualizar** `name`, `settings.services`, `settings.working_hours`, `user_id`, `is_active=true`. Si no existe → **crear**.
4. Para unidades del tenant que **no aparecen en el seeder** (profesional que se fue, reorg): marcarlas `is_active=false` en lugar de borrarlas. Sus reservas históricas se mantienen.
5. Logs claros: `created N | updated M | deactivated K`.

Si Q1 no está respondida cuando llegue este paso, crear UNA unidad placeholder con `slug: "amia-default"`, `name: "Profesional AMIA"`, `settings.services` con todos los servicios del catálogo. Permite avanzar al test E2E sin bloquear.

**Aceptación:**
- `php artisan db:seed --class=AmiaBookableUnitsSeeder` corre limpio en local y es idempotente: ejecutarlo dos veces seguidas deja el mismo estado, sin duplicados.
- Test feature `tests/Feature/AmiaBookableUnitsSeederTest.php` que verifica idempotencia y desactivación de unidades faltantes.
- En prod NO ejecutar todavía — eso es Paso 11.

---

## Paso 9 — Conectar tool calling al flujo real + selección de agente por línea

### Toggle por agente

Agregar columna `tool_calling_enabled` (boolean, default false) a `ai_agents` vía migración nueva. Esto deja activar tool calling agente por agente sin afectar a otros tenants/agentes.

Actualizar:
- `AiAgent` model: agregar a `$fillable` y casts.
- `app/Http/Requests/Api/AiAgentRequest.php`: validación.
- `app/Http/Resources/AiAgentResource.php`: exponer el flag.
- UI de configuración del agente (`resources/js/Pages/...` o componente equivalente — verificar al implementar): toggle "Habilitar herramientas (citas, contactos, seguimiento)".

### Selección por línea WhatsApp (necesario para aislamiento de pruebas)

**Hoy `ai_agents` no tiene `whatsapp_line_id`.** El método `AiAgentService::agentForTenant(string $tenantId)` (línea 31) selecciona un único agente per-tenant ordenando por `is_default` + `is_enabled` + `created_at`. Eso significa que si activamos `tool_calling_enabled=true` en el agente default del tenant, **todas las líneas del tenant lo usan al instante**. AMIA tiene dos líneas conectadas con 517 conversaciones — no podemos arriesgar.

Por eso en este paso también:

1. **Migración**: agregar `whatsapp_line_id` (uuid, nullable, FK a `whatsapp_lines`) a `ai_agents`. Index `(tenant_id, whatsapp_line_id)`. **Además, índice único parcial** que garantiza máximo un agente por línea (cuando línea no es null):

   ```php
   // En la migración:
   Schema::table('ai_agents', function (Blueprint $t) {
       $t->foreignUuid('whatsapp_line_id')->nullable()->after('tenant_id')
           ->constrained('whatsapp_lines')->nullOnDelete();
       $t->index(['tenant_id', 'whatsapp_line_id']);
   });
   // Índice único parcial Postgres (raw SQL — Schema builder no expone WHERE).
   // Nota: ai_agents NO usa SoftDeletes; no se filtra por deleted_at.
   DB::statement("
       CREATE UNIQUE INDEX ai_agents_unique_per_line
       ON ai_agents (tenant_id, whatsapp_line_id)
       WHERE whatsapp_line_id IS NOT NULL
   ");
   ```

   En `down()`: `DROP INDEX IF EXISTS ai_agents_unique_per_line` antes del `Schema::table` que quita la columna.
2. **Refactor de selección**: introducir `AiAgentService::agentForConversation(Conversation $conv): ?AiAgent` que prefiera, en este orden:
   - Agente con `whatsapp_line_id = $conv->whatsapp_line_id` y `is_enabled=true`.
   - Agente con `whatsapp_line_id = null`, `is_default=true`, `is_enabled=true` (catch-all del tenant).
   - El comportamiento previo de `agentForTenant` queda como fallback explícito.
3. **Cambiar el call site** que hoy invoca `agentForTenant` (buscar usos: webhook handler / message processor; típicamente disparado desde el flujo de inbound WhatsApp) para que reciba la `Conversation` y use `agentForConversation`. Si hay más de un call site, todos.
4. **UI**: en la pantalla de gestión de agentes, picker opcional "Línea WhatsApp" con opción "(todas)". El form request valida que la línea pertenece al tenant del usuario; la unicidad por línea está garantizada por el índice parcial de la migración (cualquier intento de duplicado lanza `QueryException`, captura en el controller y devuelve 422 con mensaje claro).

### Switch en `generateAndSend`

En `AiAgentService::generateAndSend()` (línea ~56): cuando `$agent->tool_calling_enabled === true`, llama a `generateReplyWithMeta(...)` con `useTools=true` y `conversation=$conversation`. Si `false`, comportamiento idéntico al actual.

**Aceptación:**
- Con `tool_calling_enabled=false`, regresión cero (tests existentes pasan).
- Con un agente prueba con `whatsapp_line_id` setteado y `tool_calling_enabled=true`: sólo las conversaciones de esa línea usan tool calling; las otras líneas siguen con el agente default.
- Test feature que cubre la lógica de `agentForConversation` con: (a) match exacto por línea, (b) fallback a default sin línea, (c) ningún agente disponible.

---

## Paso 10 — Test E2E

`tests/Feature/AmiaAgentE2ETest.php`:

1. Setup: tenant AMIA local + agent con `tool_calling_enabled=true` + 2 unidades profesionales con servicios distintos + `business_hours` similares a prod.
2. Mockear Anthropic para devolver una secuencia tool_use realista:
   - Mensaje paciente: "Hola, quiero una citología para esta semana en la mañana".
   - Modelo invoca `get_available_slots` → devuelve 2 slots.
   - Modelo responde texto con los 2 slots.
3. Segundo mensaje paciente: "El primero me sirve. Soy Ana Pérez, CC 123."
   - Modelo invoca `create_appointment` con el primer slot → reserva creada en `requested`.
   - Modelo responde texto de confirmación.
4. Verificar:
   - 1 `Reservation` creada con status `requested`, `bookable_unit_id` correcto, `service='citologia'`, `starts_at`/`ends_at` iguales al slot elegido.
   - **El slot que se acaba de reservar ya NO aparece** en una nueva llamada a `get_available_slots` (mismo servicio + jornada). El segundo slot, si todavía cae dentro de la ventana, sí debe seguir disponible.
   - Test adicional de concurrencia: dos invocaciones simultáneas de `CreateAppointmentTool` para el mismo slot → exactamente una crea la reserva, la otra recibe `{error: 'slot_taken'}`. Cubre tanto el path de `pg_advisory_xact_lock` como el constraint EXCLUDE (mockear el lock para forzar que la segunda llegue al insert y verificar que la `QueryException` con SQLSTATE `23P01` se traduce a `slot_taken`).

**Aceptación:** test pasa.

---

## Paso 11 — Rollout

**Bloqueado por Q3.** Pre-condición: el Paso 9 dejó `whatsapp_line_id` en `ai_agents` y `agentForConversation` operativo, lo cual habilita aislamiento real por línea.

1. Deploy a prod (rama → PR → squash merge). Migrations corren en deploy.
2. Crear/elegir **línea WhatsApp de pruebas** para AMIA (Q3). No reutilizar las dos líneas con tráfico real.
3. Crear un `AiAgent` separado para AMIA: clonar el agente actual, setear `whatsapp_line_id` = la línea de pruebas, `tool_calling_enabled=true`, `is_default=false`. Mantener el agente principal sin tool calling.
4. Correr seeder de unidades en prod (si Q1 respondida; si no, una unidad placeholder).
5. Hacer 5–10 conversaciones manuales de prueba en la línea de pruebas, cubriendo:
   - Agendamiento normal con un servicio del catálogo.
   - Servicio que requiere profesional específica (paciente la nombra).
   - Reprogramación (paciente con reserva existente — conviene sembrar una de prueba).
   - Alarma clínica (debe disparar `handoff_to_human` y poner `ai_agent_enabled=false`).
   - Pregunta de precio (no debe inventar — verificar contra el override actual de Fase 0 antes de retirarlo).
   - Mensaje a las 12:30pm pidiendo cita "ya" (no debe ofrecer slots en horario de almuerzo, validando el fix de multi-bloque del Paso 4).
6. Verificar logs y reservaciones en BD: las creadas por el AI deben estar `requested`, con `bookable_unit_id` correcto, y aparecer en `/reservations` UI.
7. **Solo si los 10 pasan**:
   - Asignar el agente con tool calling también a la línea principal (cambiar `whatsapp_line_id` o crear otra fila), o flipear `tool_calling_enabled=true` en el agente principal.
   - **Retirar el override de Fase 0** del `system_prompt` del agente que se acaba de activar (volver al prompt original de `docs/amia/prompt.md`). Hacer backup en `/tmp/amia_prompt_*_pre_phase1_rollout.txt` antes.

**Aceptación:** 10/10 conversaciones pasan; agente con tool calling activo en la línea principal; override retirado; sin regresiones en `/reservations` UI; logs de tool invocations visibles.

---

## Paso 12 — Observabilidad mínima

- Métrica diaria: # de conversaciones donde el AI invocó cada tool, # de reservaciones creadas por el AI vs por humanos, % de `requested` confirmadas por humanos.
- Query SQL ad-hoc o un comando artisan `amia:metrics`. No dashboards todavía.

**Aceptación:** comando corre y devuelve los conteos del día anterior.

---

## Cosas explícitamente fuera de scope

- Google Calendar / Cal.com sync (Fase 2).
- Working hours por unidad — solo se soporta como override opcional vía JSON; no UI dedicada.
- Múltiples servicios por reservación (1 reservación = 1 servicio).
- Lista de espera, recordatorios automáticos por SMS, no-show automático, scoring.
- Reasignación automática entre profesionales si una cancela.
- Multi-recurso (sala + profesional al tiempo).
- OpenAI tool calling — solo Anthropic en esta fase.
- Cambios de UI/labels para vertical=health (`party_size` se queda visible como está; rename de "seated" → "atendida" no es prioridad).

## Convenciones a respetar (de `CLAUDE.md`)

- `declare(strict_types=1);` en todo PHP nuevo.
- Form Requests para validación.
- Laravel Actions para business logic; controllers delgados.
- Tenant scope en cada query nueva. Nunca exponer datos cross-tenant.
- UUIDs en cada PK nueva.
- Tests feature para endpoints; tests unitarios para business logic.
- Conventional commits: `feat:`, `fix:`, `refactor:`, `test:`, `chore:`.

## Reportar al final

Al cerrar la Fase 1, entregar:
- Link al PR.
- Lista de migraciones nuevas y sus efectos.
- Resumen de las 10 conversaciones de prueba (qué pasó, captura).
- Bloqueos pendientes (si los hay).
- Recomendación para Fase 2 (Google Calendar vs otros).

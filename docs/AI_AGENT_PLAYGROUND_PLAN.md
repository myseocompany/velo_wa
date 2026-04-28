# Plan: Playground del Agente IA (chat de pruebas)

Fecha: 2026-04-26
Autor: nicolas@myseocompany.co

## Objetivo

Permitir al admin/owner probar el agente de IA configurado **sin enviar mensajes reales por WhatsApp**. Una UI de chat dentro del CRM donde se escribe un mensaje, se obtiene la respuesta del modelo (Anthropic / OpenAI / Gemini) usando el `system_prompt` y modelo del agente seleccionado, y se ve un indicador del estado del API.

### Fuera de alcance

- No crear `Conversation`, `Message`, ni disparar `SendWhatsAppMessage` ni `ConversationUpdated`.
- No tocar el flujo productivo de `AiAgentService::generateAndSend`.
- No persistir el historial del chat de prueba (solo estado en el componente React; se pierde al refrescar).
- No exponer el playground a roles `agent` — gating tanto en la **ruta web** (`routes/web.php:114`) como en el **endpoint API** (mismo gate `owner` / `admin` que la pantalla de configuración).

### Decisión: el playground NO usa fallback de provider

`AiAgentService::generateReply()` actualmente hace fallback a otros providers cuando el primario falla con 429 o tiene API key faltante (`app/Services/AiAgentService.php:148`, `:170`). En producción eso es deseable. **En el playground no**: si el admin seleccionó un modelo Anthropic y la key de Anthropic no está, debe ver el error real, no una respuesta silenciosa de OpenAI/Gemini que invalide el test.

Implementación: el método nuevo `generateReplyWithMeta()` (ver más abajo) acepta `bool $disableFallback = false`; el playground lo invoca con `true`.

## Interpretación de “Que no notifique si el API está funcionando”

Asumo: el playground debe mostrar de forma **silenciosa** un indicador de salud (ej. badge verde/rojo) y solo **notificar visiblemente cuando algo falla** (error del provider, falta de API key, 429, timeout). En el éxito no aparece toast ni alerta — la respuesta del bot por sí sola ya confirma que el API funciona. Si la intención era la opuesta (mostrar siempre estado), se cubre igual con el badge persistente.

## Diseño funcional

### Pantalla nueva: `Settings/AiAgent` (extender) o `Settings/AiAgentPlayground` (nueva)

**Recomendado:** agregar un panel/pestaña dentro de la pantalla actual `Settings/AiAgent.tsx` con título “Probar agente”. Razón: el agente seleccionado ya está cargado allí; reutilizamos `selectedAgent`, `availableModels`, y el dropdown de selección. Evita un segundo viaje al backend para cargar lo mismo.

Layout (en panel derecho o tab debajo del formulario):

```
┌──────────────────────────────────────────────┐
│ Probar “{agent.name}”   [● API OK]  [Limpiar]│
├──────────────────────────────────────────────┤
│  burbuja agente (assistant)                  │
│              burbuja usuario (user) ──>      │
│  burbuja agente …                            │
│                                              │
│  [textarea] [Enviar]                         │
└──────────────────────────────────────────────┘
```

Comportamiento:

- El historial vive solo en estado React (`messages: {role, content}[]`). Botón “Limpiar” lo vacía.
- Cada envío hace `POST /api/v1/ai-agents/{agent}/playground` con `{ message, history }`.
- Mientras espera: input deshabilitado, spinner en la burbuja del agente.
- Si el agente seleccionado tiene `system_prompt` vacío → mostrar warning amarillo arriba del chat (no bloquear el envío; dejar que el backend valide).
- Si `selectedAgent` cambia → preguntar antes de descartar el historial actual (o limpiar automáticamente; decidir en implementación).

### Indicador de estado del API

- Estado local: `'unknown' | 'ok' | 'error'`.
- En éxito → `ok` (badge verde, sin toast).
- En error → `error` (badge rojo + mensaje del error en línea sobre el chat, no toast modal).
- No hace ping proactivo; el estado se deriva del último intento.

## Diseño técnico — backend

### Endpoint nuevo

```
POST /api/v1/ai-agents/{aiAgent}/playground
```

**Middleware:** `auth:sanctum` + `tenant` + `role:admin` (mismo gating que `update`).

**Throttle:** crear el limiter `playground` en `App\Providers\AppServiceProvider::configureRateLimiting()` (20/min por tenant). Más estricto que `messages` porque cada request quema tokens del LLM. Ver el snippet completo en el paso 7 de “Pasos de implementación”.

**Request (`PlaygroundRequest`):**

```php
[
  'message' => ['required', 'string', 'max:4000'],
  'history' => ['array', 'max:50'],
  'history.*.role' => ['required', 'in:user,assistant'],
  'history.*.content' => ['required', 'string', 'max:4000'],
]
```

**Controller (`AiAgentController::playground`):**

Firma: `playground(PlaygroundRequest $request, string $aiAgent, AiAgentService $service): JsonResponse` — el servicio se inyecta por **method injection** (Laravel resuelve del container automáticamente). No usar `app(AiAgentService::class)` inline; eso permite mockear el servicio en tests con `$this->instance(AiAgentService::class, $mock)` sin construir el controller a mano.

1. Resolver `$aiAgent` con `resolveAgent()` (ya existe, valida tenant).
2. Validar `system_prompt` no vacío → 422 con `{ message: 'El agente no tiene system_prompt configurado.' }`.
3. Construir `$history = [...$request->history, ['role'=>'user','content'=>$request->message]]`.
4. Llamar `$service->generateReplyWithMeta($aiAgent, $aiAgent->system_prompt, $history, disableFallback: true, context: ['playground' => true])`.
5. Capturar `App\Exceptions\AiProviderException` → 502 con `{ message, provider, status }` (ya viene estructurado).
6. Respuesta exitosa: `{ data: { reply: string, model: string, provider: string } }`.

**No tocar `generateAndSend`.** Sí hay que tocar `AiAgentService` para introducir un método nuevo y una excepción tipada (ver siguiente sección). `generateAndSend` sigue funcionando igual. **`generateReply` se refactoriza obligatoriamente** como wrapper de `generateReplyWithMeta` — esto es DRY, no opcional: si quedan dos copias del bucle de fallback, divergen.

### Cambios en `AiAgentService` (necesarios — el contrato actual no alcanza)

El plan original asumía cosas que el código actual no expone. Cambios concretos:

1. **Nueva excepción** `app/Exceptions/AiProviderException.php`:
   ```php
   class AiProviderException extends \RuntimeException {
       public function __construct(
           public readonly string $provider,
           public readonly int $status,         // HTTP status real, o 0 si no hubo request
           public readonly ?string $body,        // raw body del provider; NUNCA se loggea ni se devuelve al frontend
           string $message,                      // mensaje sanitizado, seguro para log y para el cliente
       ) { parent::__construct($message); }
   }
   ```
   - **Reemplaza** el `\RuntimeException` plano que hoy lanza `throwProviderRequestException()` (`app/Services/AiAgentService.php:394-404`).
   - **Convención de `status`**:
     - HTTP real del provider (4xx/5xx) cuando `$response->failed()`.
     - `0` cuando no hubo respuesta HTTP útil: API key faltante, provider no soportado, **timeout** o cualquier `Illuminate\Http\Client\ConnectionException` (DNS, refused, TLS, network unreachable). El test del playground asierta `status === 0` tanto para “API key faltante” como para “timeout” (mockeable con `Http::fake(fn () => throw new ConnectionException('timeout'))`).
   - **`body` es solo para diagnóstico interno**: se guarda en la excepción pero **no** se incluye en `Log::error` (puede contener PII del request o detalles del provider) ni en la respuesta JSON del endpoint. El frontend solo recibe `{ message, provider, status }`.

2. **Nuevo método** `generateReplyWithMeta()` en `AiAgentService` con firma:
   ```php
   /** @return array{reply: string, provider: string, model: string} */
   public function generateReplyWithMeta(
       AiAgent $agent,
       string $prompt,
       array $history,
       bool $disableFallback = false,
       array $context = [],
   ): ?array;
   ```
   - Si `$disableFallback === true`: solo intenta el provider/modelo del agente; si la API key falta o el provider falla, lanza `AiProviderException` (no recorre el resto de la chain).
   - `$context` se mergea en los `Log::warning` / `Log::error` para agregar `playground=true`.
   - El loop interno extrae el `$provider` que efectivamente respondió (puede diferir del primario si fallback está habilitado y se usa fuera del playground).

3. **Refactor obligatorio**: `generateReply()` queda como wrapper de `generateReplyWithMeta()`:
   ```php
   public function generateReply(AiAgent $agent, string $prompt, array $history): ?string {
       return $this->generateReplyWithMeta($agent, $prompt, $history)['reply'] ?? null;
   }
   ```
   No es opcional. Anti-patrón a rechazar: copiar `generateReply` completo, renombrarlo `generateReplyWithMeta` y agregar el meta. Eso deja dos bucles de fallback que terminan divergiendo.

4. **`throwProviderRequestException()`** pasa a recibir `array $context = []` y a lanzar `AiProviderException` (con `status` = HTTP real del provider) en lugar de `RuntimeException`. El `Log::error` interno **deja de incluir `body`** — solo `provider`, `agent_id`, `status`, y los pares clave/valor de `$context` (ej. `playground=true`). El `body` queda únicamente accesible como propiedad de la excepción para debugging local.

5. **Captura de errores de transporte (timeout / red)**. `Http::timeout(40)->post(...)` puede lanzar `Illuminate\Http\Client\ConnectionException` **antes** de llegar a `$response->failed()`. Cada uno de los 3 métodos de provider (`generateAnthropicReply`, `generateOpenAiReply`, `generateGeminiReply`) debe envolver la llamada HTTP así:
   ```php
   try {
       $response = Http::withHeaders([...])->timeout(40)->post(...);
   } catch (\Illuminate\Http\Client\ConnectionException $e) {
       $this->throwProviderRequestException($provider, $agentId, status: 0, body: null, message: $e->getMessage(), context: $context);
   }
   ```
   Cumplir DRY: la conversión `ConnectionException` → `AiProviderException` se hace dentro de `throwProviderRequestException()` (extender su firma para aceptar `?string $body` y un mensaje base), no inline en cada método de provider.

6. **`shouldFallbackAfterProviderFailure()`** se ajusta para no asumir el shape:
   ```php
   private function shouldFallbackAfterProviderFailure(\RuntimeException $exception): bool {
       if ($exception instanceof AiProviderException) {
           return in_array($exception->status, [0, 429], true)
               || str_contains(strtolower($exception->getMessage()), 'rate_limit')
               || str_contains(strtolower($exception->getMessage()), 'insufficient_quota');
       }
       // Fallback heurístico para excepciones no tipadas (defensivo, no debería ocurrir tras la migración)
       $message = strtolower($exception->getMessage());
       return str_contains($message, '429') || str_contains($message, 'rate_limit') || str_contains($message, 'insufficient_quota');
   }
   ```
   Status `0` (API key faltante) se trata como “elegible para fallback” en producción para preservar el comportamiento actual de `generateReply` cuando se llama sin `disableFallback`.

### Logging

- El `Log::error('AiAgent: provider request failed', …)` actual recibe ahora `$context` (ver punto 4 arriba). El playground pasa `['playground' => true]` para distinguir en producción.
- **Cambio respecto al log actual**: se elimina `body` del payload del log (hoy lo incluye, ver `app/Services/AiAgentService.php:396-401`). El `body` queda solo dentro de la excepción para debugging local; nunca se persiste ni se devuelve al cliente.
- Campos que sí se loggean: `agent_id`, `provider`, `status`, más cualquier clave de `$context` (ej. `playground`).
- Campos que **nunca** se loggean: `message` y `history` del usuario, `body` del provider, contenido de respuesta del LLM.

## Diseño técnico — frontend

### Cambios en `resources/js/Pages/Settings/AiAgent.tsx`

1. Nuevo estado:
   ```ts
   const [playgroundMessages, setPlaygroundMessages] = useState<{role:'user'|'assistant', content:string}[]>([]);
   const [playgroundInput, setPlaygroundInput] = useState('');
   const [playgroundSending, setPlaygroundSending] = useState(false);
   const [apiStatus, setApiStatus] = useState<'unknown'|'ok'|'error'>('unknown');
   const [apiError, setApiError] = useState<string|null>(null);
   ```

2. Nueva función `sendPlayground()`:
   - `POST /api/v1/ai-agents/${selectedId}/playground` con `{ message, history: playgroundMessages }`.
   - On success → push assistant reply, `setApiStatus('ok')`, clear input.
   - On error → `setApiStatus('error')`, `setApiError(extractApiError(err, '...'))`. **No** mostrar toast.

3. **Estructura de componentes (decisión final, no ambigua):**
   - `<PlaygroundPanel>` vive **dentro de** `Settings/AiAgent.tsx`. Es específico de esa pantalla, no se reutiliza en otra parte → no merece archivo propio.
   - `<ChatBubble>` se crea como componente reutilizable en `resources/js/Components/Chat/ChatBubble.tsx`. Lo usa `<PlaygroundPanel>` y queda disponible para futuros chats internos (preview de quick replies, simuladores, etc.).
   - Props mínimas de `ChatBubble` (interfaz segregada, ver SOLID-I):
     ```ts
     type ChatRole = 'user' | 'assistant';
     interface ChatBubbleProps {
       role: ChatRole;
       content: string;
       meta?: string;      // ej. "respondió: anthropic / claude-haiku-4-5"
       loading?: boolean;  // muestra indicador de "escribiendo…"
     }
     ```
   - **No** importar `resources/js/Pages/Inbox/Partials/MessageThread.tsx`. Está acoplado a `conversationId`, paginación, quick replies, media upload y al tipo `Message` persistido — todo eso es exactamente lo que el playground evita por diseño.
   - **Tampoco** extraer del `MessageThread` un componente presentacional ahora. Es trabajo de refactor de Inbox que excede esta tarea; si en el futuro se hace, `ChatBubble` ya estará disponible para que el Inbox lo adopte.

### Estilos

- Reutilizar tokens de `DESIGN_SYSTEM.md` (colores `ari-*`, `brand-*`).
- Burbujas: usuario alineadas a la derecha (`bg-ari-100`), agente a la izquierda (`bg-gray-50`).
- Badge: `bg-green-100 text-green-700` / `bg-red-100 text-red-700` / `bg-gray-100 text-gray-500`.
- Iconos `lucide-react` ya importados (`Sparkles`, `Loader2`).

## Pasos de implementación (orden sugerido)

1. **Backend — Excepción:** crear `app/Exceptions/AiProviderException.php` con `provider`, `status`, `body`.
2. **Backend — Servicio:** en `app/Services/AiAgentService.php`:
   - Cambiar `throwProviderRequestException()` para aceptar `array $context = []` y lanzar `AiProviderException` en vez de `RuntimeException`.
   - Ajustar `shouldFallbackAfterProviderFailure()` para inspeccionar `$exception->status`.
   - Agregar `generateReplyWithMeta(AiAgent, string, array, bool $disableFallback = false, array $context = []): ?array` que devuelve `['reply','provider','model']`.
   - Refactorizar `generateReply()` como wrapper de `generateReplyWithMeta()` (**obligatorio** por DRY).
3. **Backend — Form Request:** crear `app/Http/Requests/Api/PlaygroundRequest.php`.
4. **Backend — Controller:** método `playground(PlaygroundRequest $request, string $aiAgent, AiAgentService $service): JsonResponse` en `AiAgentController` (method injection del servicio) que captura `AiProviderException` y devuelve 502 estructurado.
5. **Backend — Ruta API:** registrar en `routes/api.php` dentro del grupo `role:admin`:
   ```php
   Route::post('/ai-agents/{aiAgent}/playground', [AiAgentController::class, 'playground'])
       ->middleware('throttle:playground')
       ->name('ai-agents.playground');
   ```
6. **Backend — Ruta web:** agregar `role:admin` a la ruta de Settings/AiAgent en `routes/web.php:114` (hoy no lo tiene), para que un `agent` no pueda ni renderizar la pantalla del playground.
7. **Backend — Throttle:** definir `playground` rate limiter en `App\Providers\AppServiceProvider::configureRateLimiting()` (no en `RouteServiceProvider` — este proyecto centraliza los limiters en `AppServiceProvider`, ver `app/Providers/AppServiceProvider.php:40`). Ejemplo siguiendo el patrón de `messages`:
   ```php
   RateLimiter::for('playground', function (Request $request) {
       $tenantId = $request->user()?->tenant_id ?? $request->ip();
       return Limit::perMinute(20)
           ->by("playground:{$tenantId}")
           ->response(fn () => response()->json([
               'message' => 'Demasiadas pruebas. Espera un momento.',
           ], 429));
   });
   ```
8. **Backend — Tests:** `tests/Feature/Api/AiAgentPlaygroundTest.php`:
   - 200 con respuesta mockeada (`Http::fake`), verificando que el JSON incluye `provider` y `model` reales.
   - 422 si `system_prompt` vacío.
   - 502 estructurado si el provider devuelve 500 (verificar shape `{ message, provider, status }`).
   - 502 si la API key del provider del agente falta (sin fallback silencioso).
   - 403 si el rol es `agent`.
   - 404 si el `aiAgent` es de otro tenant (cross-tenant safety).
   - Assertion adicional: ningún `Conversation` ni `Message` creado tras el request.
9. **Frontend — UI:** extender `Settings/AiAgent.tsx` con panel “Probar agente”. Mostrar el `provider` realmente usado bajo cada respuesta del agente (útil cuando se permita debug futuro de fallback).
10. **Frontend — Smoke test manual:** enviar mensaje, verificar respuesta, simular error apagando la API key del provider configurado y confirmar que el badge se pone rojo y el error es claro (no rebota a otro provider).

## Riesgos y consideraciones

- **Costo de tokens**: cada mensaje consume créditos del provider. Mitigar con throttle estricto + mensaje informativo en la UI (“Cada mensaje consume créditos del proveedor configurado”).
- **Fallback de provider deshabilitado en playground**: por decisión explícita (ver “Fuera de alcance”), el playground **no** ejerce el fallback de `generateReply`. Eso garantiza que el admin vea el resultado real del provider que configuró, en vez de una respuesta silenciosa de otro provider.
- **Tenant isolation**: `resolveAgent()` ya valida que el `aiAgent` pertenece al tenant del usuario. Mantener.
- **Rate limit ante abuso**: usar el throttle `playground` separado para no contaminar `messages` ni `api`.
- **Logs**: no loggear el contenido del mensaje del usuario ni el historial. Solo `agent_id`, `provider`, `status`, `playground=true`.
- **Compatibilidad con código existente**: el cambio de `RuntimeException` → `AiProviderException` afecta a `generateAndSend` (que llama a `generateReply`, que actualmente puede propagar la `RuntimeException`). Verificar callers en `WebhookHandlerService` o donde se invoque, y confirmar que `AiProviderException extends RuntimeException` para no romper `try/catch (\RuntimeException)` existentes.
- **Deuda anotada (no se ejecuta acá)**: `AiAgentService` mezcla resolución de provider, payload por provider, transporte HTTP y fallback. Cuando llegue el 4° provider, partir en `AnthropicClient` / `OpenAiClient` / `GeminiClient` + `ProviderClient` interface. Mientras tanto se queda como está — abrir issue/TODO si se quiere trackear.

## Para delegar a un agente externo (Codex / Cursor / otro)

Este plan está escrito para que un agente de codificación lo ejecute end-to-end. Si vas a delegarlo, pásale junto al plan:

1. **`CLAUDE.md` de la raíz** — convenciones del proyecto (`declare(strict_types=1)`, Form Requests, `withoutGlobalScope('tenant')`, UUIDs, multi-tenancy, etc.). El agente no las infiere solo.
2. **Archivos clave de referencia** (para que no tenga que buscarlos):
   - `app/Services/AiAgentService.php` — firma exacta de `generateReply($agent, $prompt, $history)`.
   - `app/Http/Controllers/Api/V1/AiAgentController.php` — patrón del controller, especialmente `resolveAgent()` para tenant isolation.
   - `app/Http/Requests/Api/AiAgentRequest.php` — patrón del Form Request.
   - `routes/api.php` líneas 78-93 — sección AI agent donde registrar la nueva ruta.
   - `resources/js/Pages/Settings/AiAgent.tsx` — pantalla donde se monta el panel.
3. **Punto frecuente de error**: el throttle `playground` se define en **`App\Providers\AppServiceProvider::configureRateLimiting()`** (`app/Providers/AppServiceProvider.php:40`), **no** en `RouteServiceProvider` (este proyecto no usa ese archivo para limiters). Pasarle el archivo como referencia con los limiters existentes (`api`, `messages`, `whatsapp-control`, `webhooks`) para que copie el patrón.

## Criterios de aceptación

- [ ] Admin puede abrir Settings → Agente IA, escribir un mensaje y ver respuesta del modelo configurado.
- [ ] No se crea ninguna fila en `conversations` ni `messages` al usar el playground.
- [ ] No se dispara ningún job (`SendWhatsAppMessage`) ni evento (`ConversationUpdated`).
- [ ] Si el API key del provider configurado no existe, la UI muestra un error claro (no toast invasivo) y el badge pasa a rojo.
- [ ] Si el envío es exitoso, el badge queda verde y no aparece notificación adicional.
- [ ] Rol `agent` recibe 403 al intentar el endpoint.
- [ ] Cross-tenant: `agent_id` de otro tenant devuelve 404.
- [ ] Tests feature pasan.

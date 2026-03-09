# Auditoria tecnica de desarrollo

Fecha: 2026-03-08  
Repositorio: `/Users/projects/velo_wa`  
Branch: `main` (adelantado 8 commits sobre `origin/main`)

## Alcance y metodologia

- Revision estatica de codigo enfocada en cambios activos y flujos criticos (`API`, `jobs`, `inbox`, `quick replies`).
- Ejecucion de pruebas:
  - `php artisan test --testsuite=Feature` -> 26 pruebas OK.
  - `php artisan test --testsuite=Unit` -> 1 prueba OK.
  - `npm run build` -> compilacion TypeScript/Vite OK.

## Hallazgos

### [P1] Falla por null dereference en envio de mensajes cuando el contacto fue eliminado logicamente

- Evidencia:
  - `app/Jobs/SendWhatsAppMessage.php:35-38` accede a `$conversation->contact` y luego `$contact->tenant` antes del `try/catch`.
  - `app/Models/Conversation.php:51-54` define `contact()` sin `withTrashed()`.
  - `app/Models/Contact.php:18` usa `SoftDeletes`.
  - `app/Http/Controllers/Api/V1/ContactController.php:82-85` realiza soft delete.
- Riesgo:
  - Si el contacto fue eliminado entre la creacion del mensaje y la ejecucion del job, `$contact` puede ser `null`.
  - El error ocurre fuera del `try/catch`; el estado del mensaje puede quedar en `pending` sin transicionar a `failed`.
- Recomendacion:
  - Mover la resolucion de `conversation/contact/tenant` dentro del `try`.
  - Manejar contacto nulo explicitamente y marcar el mensaje como `failed`.
  - Evaluar `->withTrashed()` en la relacion para jobs que dependen de historico.

### [P1] Riesgo de despliegue: pagina de Quick Replies no versionada en git

- Evidencia:
  - `git status` reporta `?? resources/js/Pages/Settings/QuickReplies.tsx`.
  - `routes/web.php:58-60` renderiza `Settings/QuickReplies`.
  - `resources/js/Pages/Settings/Index.tsx:73` enlaza a `/settings/quick-replies`.
- Riesgo:
  - Si se despliega sin agregar ese archivo al control de versiones, la ruta queda rota en runtime.
- Recomendacion:
  - Versionar el archivo antes de merge/deploy.
  - Agregar check en CI para evitar releases con archivos funcionales sin trackear.

### [P2] Regresion en busqueda de telefono: consultas con simbolos pueden devolver todos los registros

- Evidencia:
  - `app/Http/Controllers/Api/V1/ContactController.php:23-29`.
  - `app/Http/Controllers/Api/V1/ConversationController.php:48-55`.
- Riesgo:
  - Si `search` contiene solo simbolos (`+`, `(`, `-`, espacios), `phoneSearch` queda vacio y se ejecuta `phone ILIKE '%%'`.
  - Resultado inesperado (listado completo) y costo innecesario de consulta.
- Recomendacion:
  - Agregar condicion: incluir filtro por `phone` solo cuando `phoneSearch !== ''`.
  - Mantener filtro por nombre/correo con el `search` original.

### [P2] Ambiguedad por mayusculas/minusculas en shortcuts de respuestas rapidas

- Evidencia:
  - `app/Http/Requests/Api/QuickReplyRequest.php:23-31` valida unicidad, pero no normaliza a minusculas.
  - `resources/js/Pages/Settings/QuickReplies.tsx:21-23` normaliza slash, no case.
  - `resources/js/Pages/Inbox/Partials/MessageThread.tsx:347-359` busca shortcut con `toLowerCase()`.
- Riesgo:
  - Pueden coexistir atajos como `Hola` y `hola` (segun colacion), y el envio por slash command puede resolver uno distinto al esperado.
- Recomendacion:
  - Normalizar `shortcut` a minusculas en backend (request/model).
  - Reforzar unicidad case-insensitive (indice funcional en `lower(shortcut)` por tenant o validacion equivalente).

### [P3] Cobertura automatizada insuficiente en flujos criticos recientes

- Evidencia:
  - `tests/Feature` contiene solo `Auth`, `Profile`, `WebhookSecurity`, `Example`.
  - No hay pruebas para envio de mensajes, media, quick replies ni busqueda normalizada.
- Riesgo:
  - Cambios en API/JOBS de mensajeria pueden romperse sin deteccion previa.
- Recomendacion:
  - Agregar pruebas de integracion para:
    - envio texto/media y transiciones de estado de `Message`,
    - comando slash (`/shortcut`) y `storeQuickReply`,
    - busqueda por telefono con input formateado/simbolos.

## Resultado general

- Estado actual: funcional en pruebas existentes y build, pero con 2 riesgos de severidad alta (`P1`) que conviene corregir antes de un despliegue a produccion.

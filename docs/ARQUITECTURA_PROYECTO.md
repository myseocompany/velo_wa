# Arquitectura del Proyecto `velo_wa`

## 1) Resumen ejecutivo
`velo_wa` es una plataforma multi-tenant para gestionar conversaciones de WhatsApp, contactos y pipeline comercial.

La arquitectura combina:
- Backend monolítico en Laravel (API + lógica de negocio + webhooks).
- Frontend React + Inertia.js (SPA server-driven).
- Procesamiento asíncrono con colas.
- Tiempo real con WebSockets (eventos de conversaciones/mensajes/estado WhatsApp).
- Integración externa con Evolution API para enviar/recibir mensajes de WhatsApp.

## 2) Arquitectura de alto nivel

```text
[ Navegador ]
  React + Inertia + TypeScript + Tailwind
        |
        | HTTP (sesión/cookies + API)
        v
[ Laravel 12 ]
  - Rutas web (Inertia)
  - API v1 (Sanctum + middleware tenant)
  - Webhook de Evolution API
  - Servicios y Actions de dominio
  - Eventos broadcast
  - Jobs (colas)
        |
        |------------------------------|
        v                              v
[ PostgreSQL ]                    [ Redis ]
  datos de negocio                 cola/cache
        |
        v
[ S3/MinIO ]
  almacenamiento de media

[ Evolution API ] <-> Red de WhatsApp
        |
        v
   Webhook hacia Laravel
```

## 3) Capas y módulos principales

### 3.1 Backend (Laravel)
- Entrada web: `routes/web.php` renderiza páginas Inertia (`Dashboard`, `Inbox`, `Contacts`, `Pipeline`, `Settings`).
- Entrada API: `routes/api.php` expone `/api/v1/*` para conversaciones, mensajes, contactos, pipeline y estado/conexión de WhatsApp.
- Entrada webhook: `POST /api/v1/webhooks/evolution` en `WebhookController` valida API key y delega a `WebhookHandlerService`.

### 3.2 Dominio de negocio
- Modelos clave: `Tenant`, `User`, `Contact`, `Conversation`, `Message`, `PipelineDeal`, `AssignmentRule`, `Automation`, `WebhookLog`.
- Multi-tenancy: trait `BelongsToTenant` aplica scope global por `tenant_id` y asigna `tenant_id` en creación cuando hay usuario autenticado.
- Middleware de contexto: `EnsureTenantContext` bloquea usuarios sin tenant o inactivos.

### 3.3 Mensajería WhatsApp
- Cliente HTTP: `WhatsAppClientService` encapsula llamadas a Evolution API (`instance/create`, `connect`, `sendText`, `getBase64FromMediaMessage`, etc.).
- Recepción de mensajes: `WebhookHandlerService` enruta eventos y dispara `HandleInboundMessage` a cola `whatsapp`.
- Envío de mensajes: `MessageController@store` crea mensaje `pending` y despacha `SendWhatsAppMessage`.
- Estados de entrega: `messages.update` actualiza `pending/sent/delivered/read`.

### 3.4 Asincronía y procesamiento en background
- Jobs principales:
  - `HandleInboundMessage`: normaliza payload, crea/actualiza contacto y conversación, guarda mensaje.
  - `SendWhatsAppMessage`: envía texto por Evolution API y actualiza estado.
  - `DownloadMessageMedia`: descarga media en base64 y la guarda en `s3`.
- Cola usada: `whatsapp` sobre conexión Redis (configurable por `.env`).
- Worker Docker: servicio `queue-worker` ejecuta `php artisan queue:work`.

### 3.5 Tiempo real
- Eventos broadcast:
  - `MessageReceived` (`message.received`)
  - `ConversationUpdated` (`conversation.updated`)
  - `WaStatusUpdated` (`wa.status.updated`)
- Canal: privado por tenant (`tenant.{tenantId}`) y por conversación (`conversation.{conversationId}`).
- Cliente frontend: `laravel-echo` + `pusher-js` con broadcaster `reverb`.

### 3.6 Frontend
- Entrypoint: `resources/js/app.tsx` con `createInertiaApp`.
- UI por páginas: `resources/js/Pages/*`.
- Transporte HTTP: `axios` con CSRF/cookies.
- Hook realtime: `useTenantChannel` en `resources/js/hooks/useEcho.ts`.

## 4) Flujos críticos

### 4.1 Mensaje entrante (cliente -> WhatsApp -> sistema)
1. WhatsApp entrega evento a Evolution API.
2. Evolution API llama webhook de Laravel.
3. Laravel autentica webhook por API key.
4. `WebhookHandlerService` detecta `messages.upsert` y encola `HandleInboundMessage`.
5. Job crea/actualiza contacto, conversación y mensaje.
6. Se emiten eventos broadcast para refrescar inbox en tiempo real.

### 4.2 Mensaje saliente (agente -> sistema -> WhatsApp)
1. Frontend llama `POST /api/v1/conversations/{id}/messages`.
2. Backend crea mensaje `out` con estado `pending`.
3. Se encola `SendWhatsAppMessage`.
4. Job envía texto por Evolution API.
5. Se actualiza estado a `sent` (y luego `delivered/read` por webhook `messages.update`).

### 4.3 Conexión de instancia WhatsApp
1. Usuario ejecuta `POST /api/v1/whatsapp/connect`.
2. Backend crea instancia (`tenant_xxxxxxxx`) en Evolution API.
3. Devuelve QR base64 y marca estado `qr_pending`.
4. Cuando Evolution reporta `connection.update(open)`, se actualiza tenant a `connected` y se emite evento en tiempo real.

## 5) Persistencia de datos
- Base relacional principal: PostgreSQL.
- Estrategia multi-tenant: `tenant_id` en tablas de dominio + scoping por modelo.
- IDs: UUIDs en entidades principales.
- Historial técnico: `webhook_logs` almacena payloads de eventos entrantes.

## 6) Infraestructura local (Docker Compose)
Servicios definidos:
- `postgres` (`postgres:16-alpine`) para datos de negocio y DB adicional de Evolution.
- `redis` (`redis:7-alpine`) para colas/cache.
- `evolution-api` (`evoapicloud/evolution-api:v2.3.7`) integración WhatsApp.
- `queue-worker` (imagen de la app Laravel) para ejecutar jobs.
- `minio` para almacenamiento compatible S3.
- `mailpit` para pruebas de correo.

## 7) Tecnologías utilizadas y para qué sirve cada una

### Backend y lenguaje
- **PHP 8.2+**: runtime del backend.
- **Laravel 12**: framework principal (routing, Eloquent, DI, colas, eventos, auth, config).

### API, sesión y seguridad
- **Laravel Sanctum**: autenticación para SPA/API (`auth:sanctum`).
- **Middleware de tenant**: asegura aislamiento lógico por organización.

### Frontend
- **React 18**: construcción de interfaz por componentes.
- **Inertia.js (React adapter)**: puente entre backend Laravel y frontend SPA sin construir una API separada para vistas.
- **TypeScript 5**: tipado estático en frontend.
- **Vite 7 + laravel-vite-plugin**: bundling y dev server.
- **Tailwind CSS 3**: utilidades de estilo.
- **Headless UI**: componentes accesibles sin estilos predeterminados.

### Tiempo real
- **Laravel Reverb**: servidor de broadcasting/WebSocket en el ecosistema Laravel.
- **Laravel Echo**: cliente JS para suscribirse a canales/eventos.
- **Pusher JS protocol client**: transporte compatible usado por Echo.

### Datos, colas y almacenamiento
- **PostgreSQL 16**: base de datos principal.
- **Redis 7**: broker de colas y cache.
- **S3 (Flysystem AWS S3 v3)**: abstracción de almacenamiento de archivos/media.
- **MinIO**: implementación S3-compatible para entorno local.

### Integración WhatsApp
- **Evolution API (v2.3.7 en compose)**: capa que conecta el sistema con WhatsApp (instancias, QR, envío, eventos webhook).

### Librerías de soporte de dominio
- **spatie/laravel-data**: DTOs/objetos de datos.
- **spatie/laravel-query-builder**: filtros/ordenamiento/include en queries API.
- **spatie/laravel-permission**: modelo de permisos/roles (si se habilita en negocio).
- **spatie/laravel-activitylog**: auditoría de actividad.
- **tightenco/ziggy**: compartir rutas Laravel hacia frontend.
- **predis/predis**: cliente Redis en PHP cuando aplica.

### Frontend utilitario
- **axios**: cliente HTTP.
- **zustand**: estado local ligero.
- **recharts**: gráficas.
- **@hello-pangea/dnd**: drag & drop.
- **react-hot-toast**: notificaciones.
- **lucide-react**: iconografía.
- **date-fns**: manejo de fechas.
- **clsx / tailwind-merge / class-variance-authority**: composición de clases y variantes de componentes.

### Calidad y pruebas
- **Pest + PHPUnit**: pruebas automáticas.
- **Larastan (PHPStan para Laravel)**: análisis estático.
- **Laravel Pint**: formato y estilo de código.

## 8) Observaciones técnicas importantes
- El repositorio tiene documentación previa en `docs/` con partes desactualizadas (por ejemplo versiones de Laravel/Evolution API). Este documento refleja el estado actual del código (`composer.json`, `package.json`, `docker-compose.yml`).
- El paquete `laravel/horizon` está instalado, pero en `docker-compose.yml` actual se usa un `queue-worker` con `queue:work` (no un servicio dedicado a `horizon`).

## 9) Ubicación de archivos clave
- API: `routes/api.php`
- Web (Inertia): `routes/web.php`
- Webhook: `app/Http/Controllers/WebhookController.php`
- Manejo de webhooks: `app/Services/WebhookHandlerService.php`
- Cliente Evolution API: `app/Services/WhatsAppClientService.php`
- Jobs: `app/Jobs/*.php`
- Eventos realtime: `app/Events/*.php`
- Frontend app: `resources/js/app.tsx`
- Hook realtime frontend: `resources/js/hooks/useEcho.ts`
- Infra local: `docker-compose.yml`

# Phase 7: Team & Settings — Implementation Plan

## Estado actual (lo que ya existe)

- `Team/Index.tsx` — tabla de workload/rendimiento, **sin acciones de gestión**
- `Profile/Edit.tsx` — Breeze estándar (nombre, email, contraseña, borrar cuenta), usa `AuthenticatedLayout` en lugar de `AppLayout`
- `HandleInertiaRequests` — ya comparte `auth.user` (con `role`) e `auth.tenant` al frontend
- Tenant model — ya tiene: `timezone`, `business_hours`, `auto_close_hours`, `max_agents`, `max_contacts`
- spatie/laravel-activitylog — instalado, no conectado a modelos

---

## Tareas en orden

### 1. Team Management (invitar, editar rol, desactivar)

**Backend**
- `TeamController`: añadir `invite()`, `update()`, `deactivate()`
- `POST /api/v1/team/invite` (admin) — crea user + envía email de bienvenida via Laravel password reset
- `PATCH /api/v1/team/{user}` (admin) — actualiza `role` y `max_concurrent_conversations`
- `DELETE /api/v1/team/{user}` (admin) — `is_active = false` (no eliminar)
- Verificar límite `max_agents` antes de crear
- FormRequest: `InviteTeamMemberRequest`

**Frontend**
- Mover la gestión del equipo a `Settings/Team.tsx` (página nueva)
- `Team/Index.tsx` queda como vista de workload/performance (sin cambios)
- `Settings/Team.tsx`: tabla de miembros con rol + estado + acciones (editar, desactivar), modal de invitación
- Ruta web: `GET /settings/team`

---

### 2. RBAC en frontend

- `auth.user.role` ya llega vía Inertia — solo leerlo
- `AppLayout`: ocultar ítems de nav admin-only para agentes (Settings de gestión, Settings/Team)
- Pages admin-only: mostrar banner "sin permisos" si agente navega directamente
- No bloquear en router (ya está en backend) — solo UX

---

### 3. Tenant Settings (timezone, horario, auto-close)

**Backend**
- `TenantSettingsController`: `show()` + `update()` (owner only via `role:owner`)
- `GET /api/v1/tenant/settings`
- `PATCH /api/v1/tenant/settings` — valida timezone (lista PHP), business_hours (estructura), auto_close_hours
- Ruta API nueva

**Frontend**
- `Settings/General.tsx` (página nueva)
- Sección Zona horaria: dropdown con timezones comunes (América)
- Sección Horario laboral: toggle por día + inputs hora apertura/cierre (mismo diseño que ya tiene Automations para outside_hours)
- Sección Auto-cierre: input número de horas (0 = desactivado)
- Ruta web: `GET /settings/general`

---

### 4. Notification preferences

**Backend**
- Migración: columna `notification_preferences JSON DEFAULT '{}' NOT NULL` en `users`
- `PATCH /api/v1/profile/notifications` en `ProfileController`
- Estructura: `{ sound: bool, desktop: bool, email_on_assign: bool }`

**Frontend**
- Sección en `Profile/Edit.tsx` (ya existe, solo añadir)

---

### 5. Plan limits enforcement

- Inline en `TeamController.invite()`: abort 422 si `users.count() >= tenant.max_agents` (cuando max_agents > 0)
- Inline en `ContactController.store()`: mismo para `max_contacts`
- Mensaje de error legible: "Límite de agentes alcanzado (máx. N)"

---

### 6. Profile settings (nombre, contraseña, avatar)

**Backend**
- Migración: columna `avatar_url VARCHAR(500) NULL` en `users`
- `POST /api/v1/profile/avatar` — upload a S3, guarda URL en user
- `ProfileController`: método `uploadAvatar()`

**Frontend**
- `Profile/Edit.tsx`: migrar de `AuthenticatedLayout` → `AppLayout`
- Añadir sección de avatar: foto actual + botón upload
- Formularios existentes (nombre/email, contraseña) ya funcionan — solo re-estilizar dentro de AppLayout

---

### 7. Activity log

**Backend**
- Añadir `LogsActivity` trait a: `Contact`, `Conversation`, `PipelineDeal`
- `GET /api/v1/activity` (admin) — últimas 100 entradas del tenant, paginado
- `ActivityController` nuevo

**Frontend**
- `Settings/ActivityLog.tsx` (página nueva)
- Tabla: who + action + subject + when
- Ruta web: `GET /settings/activity`

---

### 8. Error pages (404, 500, 403)

**Backend**
- `bootstrap/app.php`: configurar `withExceptions` para renderizar páginas Inertia en errores HTTP
- O bien: `app/Exceptions/Handler.php` (render)

**Frontend**
- `resources/js/Pages/Error.tsx` — componente genérico que recibe `status` (404/500/403)
- Mensaje amigable por código, botón "Volver al inicio"

---

### 9. Loading states, empty states, skeleton screens

- Review sistemático de: Contacts/Index, Pipeline/Index, Dashboard
- Añadir esqueletos donde hay spinners solos
- Empty states con ilustración/icono + CTA donde falta ("Sin contactos aún — crea uno")

---

### 10. Mobile responsive polish

- `AppLayout`: sidebar colapsable en móvil (hamburger menu)
- Tablas largas: horizontal scroll en móvil
- Modales: scroll interno en pantallas pequeñas

---

### 11. Tests (críticos)

- `TeamManagementTest`: invite, update role, deactivate, plan limit
- `TenantSettingsTest`: get, update, owner-only guard
- `ProfileTest`: avatar upload, notification prefs
- `ActivityLogTest`: entries created on model changes, admin-only

---

## Archivos nuevos

| Archivo | Descripción |
|---------|-------------|
| `app/Http/Controllers/Api/V1/TenantSettingsController.php` | GET/PATCH tenant settings |
| `app/Http/Controllers/Api/V1/ActivityController.php` | GET activity log |
| `app/Http/Requests/Api/InviteTeamMemberRequest.php` | Validación invite |
| `app/Http/Requests/Api/UpdateTeamMemberRequest.php` | Validación update role |
| `app/Http/Requests/Api/TenantSettingsRequest.php` | Validación settings |
| `database/migrations/..._add_avatar_url_to_users.php` | Columna avatar |
| `database/migrations/..._add_notification_preferences_to_users.php` | Columna notif prefs |
| `resources/js/Pages/Settings/Team.tsx` | Gestión del equipo |
| `resources/js/Pages/Settings/General.tsx` | Settings del tenant |
| `resources/js/Pages/Settings/ActivityLog.tsx` | Log de actividad |
| `resources/js/Pages/Error.tsx` | Error pages 404/500/403 |

## Archivos modificados

| Archivo | Cambio |
|---------|--------|
| `app/Http/Controllers/Api/V1/TeamController.php` | +invite, +update, +deactivate |
| `app/Http/Controllers/ProfileController.php` | +uploadAvatar, +updateNotifications |
| `app/Models/Contact.php` | +LogsActivity |
| `app/Models/Conversation.php` | +LogsActivity |
| `app/Models/PipelineDeal.php` | +LogsActivity |
| `app/Models/User.php` | +avatar_url, +notification_preferences en fillable/casts |
| `app/Http/Middleware/HandleInertiaRequests.php` | Ya listo |
| `bootstrap/app.php` | Error handling con Inertia |
| `routes/api.php` | +team CRUD, +tenant/settings, +profile/avatar, +activity |
| `routes/web.php` | +/settings/team, +/settings/general, +/settings/activity |
| `resources/js/Pages/Profile/Edit.tsx` | AppLayout + avatar + notif prefs |
| `resources/js/Layouts/AppLayout.tsx` | RBAC nav + mobile sidebar |
| `resources/js/Pages/Settings/Index.tsx` | +links a páginas nuevas |

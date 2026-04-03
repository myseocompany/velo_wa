# AriCRM — Implementación de Vertical Restaurantes + Gaps MVP

> **Propósito:** Este documento es un prompt ejecutable para Claude Code. Cada sección es una tarea independiente que se puede implementar en orden. El LLM debe leer todo el documento antes de empezar y preguntar si hay ambigüedades.

---

## 0. Contexto del proyecto

AriCRM es un micro SaaS multi-tenant B2B que conecta números de WhatsApp Business a un CRM liviano. Cada tenant escanea un QR, conecta su número, y gestiona todo desde una sola interfaz web.

### Stack actual (NO cambiar)

| Capa | Tecnología |
|---|---|
| Backend | Laravel 11 (PHP 8.3+) |
| Frontend | React + Inertia.js |
| Base de datos | PostgreSQL |
| Cache/Colas | Redis |
| WebSockets | Laravel Reverb |
| WhatsApp Bridge | Evolution API (Baileys) en `wa.aricrm.co` |
| Storage | S3-compatible |
| Contenedores | Docker Swarm + Portainer + Traefik |
| Billing | Laravel Cashier + Stripe |

### Lo que YA existe y funciona

- Inbox conversacional en tiempo real (texto, imágenes, video, audio, documentos, stickers)
- Asignación de conversaciones a agentes, cerrar/reabrir tickets
- Gestión de contactos con tags (JSONB + GIN index), filtros multi-tag, búsqueda, paginación
- Pipeline de ventas Kanban con deals, etapas configurables, valor monetario
- Automatizaciones por evento (nueva conversación, palabra clave, fuera de horario)
- Dashboard con Δt1, conversaciones por estado, actividad de agentes
- Gestión de equipo: roles owner/admin/agent, invitaciones, RBAC
- Billing con Stripe (planes, límites por plan)
- Superadmin panel
- Plantillas de respuesta rápida
- Onboarding con escaneo QR
- Soporte multimedia completo (incluyendo PTT/audio nativo)

### Lo que NO existe

- Tareas/recordatorios (ni migración, ni modelo, ni API, ni UI)
- Features verticales para restaurantes
- Knowledge base / documentación de usuario
- Onboarding self-service completo (necesita guía manual aún)

---

## 1. TAREAS Y RECORDATORIOS

> **Prioridad:** CRÍTICA — es el último gap funcional del MVP core.

### 1.1 Migración

```
Tabla: tasks
```

| Columna | Tipo | Notas |
|---|---|---|
| `id` | uuid PK | Consistente con el resto del proyecto |
| `tenant_id` | uuid | FK tenants.id CASCADE DELETE |
| `user_id` | uuid | FK users.id — quién la creó |
| `assigned_to` | uuid nullable | FK users.id SET NULL — quién la debe hacer |
| `contact_id` | uuid nullable | FK contacts.id SET NULL |
| `conversation_id` | uuid nullable | FK conversations.id SET NULL |
| `deal_id` | uuid nullable | FK deals.id SET NULL |
| `title` | string(255) | Requerido |
| `description` | text nullable | |
| `due_at` | timestamp nullable | Fecha/hora límite |
| `reminded_at` | timestamp nullable | Cuándo se envió el último reminder |
| `completed_at` | timestamp nullable | null = pendiente |
| `priority` | enum('low','medium','high') | Default 'medium' |
| `timestamps` | | created_at, updated_at |

Índices:
- `(tenant_id, assigned_to, completed_at)` — listar tareas pendientes por agente
- `(tenant_id, due_at)` — para el scheduler de recordatorios
- `(contact_id)` — tareas de un contacto específico

### 1.2 Modelo `Task`

```php
// app/Models/Task.php
// Scoped por tenant (usar el trait de tenant scoping que ya exista en el proyecto)
// Relaciones: belongsTo User (creator), belongsTo User (assignee), belongsTo Contact, belongsTo Conversation, belongsTo Deal
// Scope: pendientes(), vencidas(), hoy(), estaSemana()
// Cast: due_at → datetime, completed_at → datetime
```

### 1.3 API endpoints

```
POST   /api/tasks              — Crear tarea
GET    /api/tasks              — Listar (filtros: assigned_to, contact_id, deal_id, status=pending|completed|overdue, sort)
GET    /api/tasks/{task}       — Detalle
PUT    /api/tasks/{task}       — Actualizar
DELETE /api/tasks/{task}       — Eliminar
PATCH  /api/tasks/{task}/complete   — Marcar completa (set completed_at = now())
PATCH  /api/tasks/{task}/reopen     — Reabrir (set completed_at = null)
```

Validación: `title` required max:255, `due_at` nullable date after:now (solo al crear), `priority` in:low,medium,high, `assigned_to` must exist in tenant users.

Policy: solo usuarios del mismo tenant. Admin/owner pueden ver todas. Agent solo las asignadas a sí mismo.

### 1.4 Recordatorios automáticos

Crear un comando Artisan `tasks:send-reminders` que corra cada 5 minutos vía scheduler:

1. Buscar tareas donde `due_at` es en los próximos 30 minutos Y `reminded_at` es null Y `completed_at` es null.
2. Por cada tarea: enviar notificación al `assigned_to` vía broadcast (Reverb) para que aparezca como toast/notificación en la UI.
3. Actualizar `reminded_at = now()`.
4. **NO enviar por WhatsApp al usuario** — solo notificación in-app por ahora.

Registrar en `app/Console/Kernel.php`:
```php
$schedule->command('tasks:send-reminders')->everyFiveMinutes();
```

### 1.5 UI (React + Inertia)

**Vista principal: `/tasks`**
- Lista de tareas con tabs: "Pendientes", "Hoy", "Vencidas", "Completadas"
- Cada tarjeta muestra: título, contacto asociado (si hay), fecha, prioridad (badge color), asignado a
- Botón de check para completar inline
- Filtro por agente asignado (dropdown)
- Botón "Nueva tarea" abre modal

**Modal crear/editar tarea:**
- Campos: título, descripción (textarea), fecha/hora (datetime picker), prioridad (select), asignar a (select de usuarios del tenant), contacto (search select), deal (search select)
- El contacto y deal son opcionales pero si se llena uno, ofrecer autocompletar el otro

**Integración con vistas existentes:**
- En la vista de detalle de **contacto**: sección/tab "Tareas" que muestra las tareas asociadas a ese contacto
- En la vista de **conversación/inbox**: botón "Crear tarea" que pre-llena el contact_id de la conversación activa
- En la vista de **deal**: sección "Tareas" con las tareas del deal
- En el **dashboard**: widget "Tareas vencidas" con contador y link a `/tasks?filter=overdue`

### 1.6 Tests

```
Feature tests con Pest:
- Crear tarea, verificar que queda asociada al tenant correcto
- Agent no puede ver tareas de otro agent
- Completar tarea setea completed_at
- Filtro por status funciona
- Reminder command solo procesa tareas no recordadas
- Tenant isolation: tenant A no ve tareas de tenant B
```

---

## 2. VERTICAL RESTAURANTES — Features específicas

> **Prioridad:** ALTA — esto es lo que diferencia a AriCRM de Kommo a $15/mes. Sin esto, no hay argumento de precio.

### 2.1 Menú digital por WhatsApp

**Concepto:** El restaurante sube su menú a AriCRM. Cuando un cliente escribe "menú" o "carta" por WhatsApp, recibe automáticamente el menú con precios.

#### 2.1.1 Migración

```
Tabla: menu_categories
```

| Columna | Tipo | Notas |
|---|---|---|
| `id` | uuid PK | |
| `tenant_id` | uuid | FK tenants.id CASCADE DELETE |
| `name` | string(100) | Ej: "Entradas", "Platos fuertes", "Bebidas" |
| `sort_order` | int unsigned | Default 0 |
| `is_active` | boolean | Default true |
| `timestamps` | | |

```
Tabla: menu_items
```

| Columna | Tipo | Notas |
|---|---|---|
| `id` | uuid PK | |
| `tenant_id` | uuid | FK tenants.id CASCADE DELETE |
| `menu_category_id` | uuid | FK menu_categories.id CASCADE DELETE |
| `name` | string(200) | Ej: "Bandeja paisa" |
| `description` | text nullable | Ej: "Frijoles, arroz, chicharrón, huevo, aguacate" |
| `price` | decimal(10,2) | |
| `currency` | string(3) | Default 'COP' |
| `image_url` | string(500) nullable | URL en S3 |
| `is_available` | boolean | Default true — para 86'd items |
| `sort_order` | int unsigned | Default 0 |
| `timestamps` | | |

#### 2.1.2 API

```
POST   /api/menu/categories              — CRUD categorías
GET    /api/menu/categories
PUT    /api/menu/categories/{id}
DELETE /api/menu/categories/{id}
PATCH  /api/menu/categories/reorder       — { ids: [3, 1, 2] }

POST   /api/menu/items                    — CRUD items
GET    /api/menu/items?category_id=X
PUT    /api/menu/items/{id}
DELETE /api/menu/items/{id}
PATCH  /api/menu/items/{id}/toggle        — Toggle is_available
PATCH  /api/menu/items/reorder            — { ids: [5, 3, 8] }
```

#### 2.1.3 Generador de mensaje WhatsApp

Crear un servicio `MenuFormatter` que genere el menú como texto plano formateado para WhatsApp:

```
🍽️ *Menú del día — [Nombre del restaurante]*

*🥗 Entradas*
• Empanadas de pipián — $8.000
• Patacones con hogao — $6.500

*🍖 Platos fuertes*
• Bandeja paisa — $22.000
• Trucha al ajillo — $18.000
  _Incluye arroz, ensalada y jugo_

*🍹 Bebidas*
• Jugo natural — $5.000
• Limonada de coco — $6.000

📲 Para pedir, escribe el nombre del plato
```

Reglas:
- Solo incluir categorías activas con al menos 1 item disponible
- Usar negritas de WhatsApp (`*texto*`) y cursiva (`_texto_`)
- Si un item tiene description, mostrarla en cursiva debajo
- Máximo 1024 caracteres por mensaje (límite WhatsApp). Si excede, dividir en múltiples mensajes.

#### 2.1.4 Integración con automatizaciones existentes

Agregar una nueva **acción de automatización**: `send_menu`. Cuando una regla se dispara (ej: keyword "menú" o "carta" o "que tienen"), ejecuta el `MenuFormatter` y envía el resultado al contacto.

Esto debe integrarse con el sistema de automatizaciones que ya existe. Buscar cómo están definidas las acciones actuales (probablemente un enum o clase de acción) y agregar `send_menu` como nueva opción.

#### 2.1.5 UI de gestión de menú

**Ruta: `/settings/menu`** (dentro de configuración del tenant)

- Vista con las categorías como columnas o secciones colapsables
- Drag & drop para reordenar categorías e items (usar la misma librería de DnD del pipeline Kanban)
- Cada item: nombre, precio, descripción, toggle de disponibilidad (switch), botón editar, botón eliminar
- Upload de imagen por item (opcional, usa el mismo sistema de upload a S3 que ya exista)
- Botón "Vista previa" que muestra cómo se verá el mensaje de WhatsApp
- Botón "Enviar menú de prueba" que lo envía al WhatsApp del owner

### 2.2 Pedidos por WhatsApp

**Concepto:** Cuando un cliente escribe el nombre de un plato, AriCRM crea un pedido asociado al contacto.

#### 2.2.1 Migración

```
Tabla: orders
```

| Columna | Tipo | Notas |
|---|---|---|
| `id` | uuid PK | |
| `tenant_id` | uuid | FK tenants.id CASCADE DELETE |
| `contact_id` | uuid | FK contacts.id |
| `conversation_id` | uuid nullable | FK conversations.id SET NULL |
| `order_number` | string(20) | Auto-generado: "ORD-001", secuencial por tenant |
| `status` | enum('pending','confirmed','preparing','ready','delivered','cancelled') | Default 'pending' |
| `notes` | text nullable | Notas del cliente o del restaurante |
| `subtotal` | decimal(10,2) | Calculado de order_items |
| `total` | decimal(10,2) | = subtotal (sin impuestos en MVP) |
| `currency` | string(3) | Default 'COP' |
| `timestamps` | | |

```
Tabla: order_items
```

| Columna | Tipo | Notas |
|---|---|---|
| `id` | uuid PK | |
| `order_id` | uuid | FK orders.id CASCADE DELETE |
| `menu_item_id` | uuid nullable | FK menu_items.id SET NULL — null si fue borrado |
| `name` | string(200) | Copiado del menu_item al crear (snapshot) |
| `quantity` | int unsigned | Default 1 |
| `unit_price` | decimal(10,2) | Copiado del menu_item al crear |
| `subtotal` | decimal(10,2) | quantity * unit_price |
| `timestamps` | | |

#### 2.2.2 Flujo de pedido

Este NO es un flujo automático por IA. Es un flujo manual asistido:

1. Cliente escribe "quiero una bandeja paisa y una limonada"
2. El **agente humano** en el inbox ve el mensaje
3. El agente hace clic en botón "Crear pedido" en el panel lateral de la conversación
4. Se abre un modal donde el agente busca items del menú, ajusta cantidades, y confirma
5. Se crea el pedido con status `pending`
6. El agente puede enviar un mensaje de confirmación al cliente con el resumen del pedido (botón "Enviar resumen")

Mensaje de confirmación generado:

```
✅ *Pedido #ORD-023 confirmado*

• 1x Bandeja paisa — $22.000
• 1x Limonada de coco — $6.000

*Total: $28.000*

Te avisamos cuando esté listo 🕐
```

#### 2.2.3 API

```
POST   /api/orders                  — Crear pedido (body: contact_id, conversation_id, items[{menu_item_id, quantity}])
GET    /api/orders                  — Listar (filtros: status, contact_id, date_from, date_to)
GET    /api/orders/{order}          — Detalle con items
PATCH  /api/orders/{order}/status   — Cambiar status (body: { status: 'confirmed' })
DELETE /api/orders/{order}          — Solo si status=pending
```

Al cambiar status a `confirmed`, `ready`, o `delivered`: disparar un evento que el frontend pueda escuchar vía Reverb para actualizar en tiempo real.

#### 2.2.4 UI

**Panel lateral en conversación (inbox):**
- Botón "Crear pedido" visible cuando hay un contacto asociado a la conversación
- Historial de pedidos del contacto visible en panel lateral

**Vista `/orders`:**
- Tabla con columnas: #, Cliente, Items (resumen), Total, Status (badge color), Fecha
- Filtros: status, rango de fechas
- Click en fila abre detalle
- Botones para cambiar status con confirmación

**Widget en dashboard:**
- "Pedidos hoy": contador
- "Ventas del día": suma de totales de pedidos delivered hoy

### 2.3 Reservas

**Concepto:** El restaurante configura horarios disponibles. Los clientes pueden reservar por WhatsApp y el sistema gestiona la disponibilidad.

#### 2.3.1 Migración

```
Tabla: reservation_settings (una fila por tenant)
```

| Columna | Tipo | Notas |
|---|---|---|
| `id` | uuid PK | |
| `tenant_id` | uuid unique | FK tenants.id CASCADE DELETE |
| `is_enabled` | boolean | Default false |
| `max_party_size` | int unsigned | Default 10 |
| `advance_days` | int unsigned | Default 7 — cuántos días adelante se puede reservar |
| `slot_duration_minutes` | int unsigned | Default 120 |
| `tables_available` | int unsigned | Default 10 — capacidad simultánea simplificada |
| `timestamps` | | |

```
Tabla: reservation_hours (horarios por día de semana)
```

| Columna | Tipo | Notas |
|---|---|---|
| `id` | uuid PK | |
| `reservation_setting_id` | uuid | FK reservation_settings.id CASCADE DELETE |
| `day_of_week` | tinyint unsigned | 0=domingo, 6=sábado |
| `open_time` | time | Ej: 12:00 |
| `close_time` | time | Ej: 22:00 |
| `is_open` | boolean | Default true |

> **Nota:** `reservation_hours` no tiene `tenant_id` directo, se accede siempre a través de `reservation_settings`. Asegurarse de que todos los queries de disponibilidad filtren por tenant primero vía join, nunca consultando `reservation_hours` en forma aislada.

```
Tabla: reservations
```

| Columna | Tipo | Notas |
|---|---|---|
| `id` | uuid PK | |
| `tenant_id` | uuid | FK tenants.id CASCADE DELETE |
| `contact_id` | uuid | FK contacts.id |
| `conversation_id` | uuid nullable | FK conversations.id SET NULL |
| `reservation_number` | string(20) | Auto: "RES-001" secuencial por tenant |
| `date` | date | |
| `time` | time | |
| `party_size` | int unsigned | |
| `status` | enum('pending','confirmed','cancelled','no_show','completed') | Default 'pending' |
| `notes` | text nullable | |
| `confirmed_at` | timestamp nullable | |
| `timestamps` | | |

#### 2.3.2 Flujo

Similar a pedidos — es manual asistido:

1. Cliente escribe "quiero reservar para 4 personas mañana a las 7pm"
2. Agente ve el mensaje, hace clic en "Crear reserva" en panel lateral
3. Modal con: fecha, hora (select de slots disponibles), número de personas, notas
4. El sistema valida disponibilidad: `reservations WHERE date = X AND time = Y AND status IN (pending, confirmed)` < `tables_available`
5. Si hay cupo, crea la reserva y ofrece botón "Enviar confirmación"

Mensaje:

```
📋 *Reserva #RES-015 confirmada*

📅 Sábado 5 de abril
🕖 7:00 PM
👥 4 personas

¡Te esperamos! Si necesitas cancelar, escríbenos con tiempo.
```

#### 2.3.3 Recordatorio automático de reserva

Agregar al scheduler un comando `reservations:send-reminders`:
- 3 horas antes de la reserva, si status=confirmed y no se ha recordado
- Envía mensaje WhatsApp al contacto vía Evolution API:

```
⏰ *Recordatorio de reserva*

Tu reserva en [Restaurante] es hoy a las 7:00 PM para 4 personas.

¿Confirmas asistencia? Responde *SÍ* o *NO*
```

- Marcar como recordada para no re-enviar

#### 2.3.4 API

```
GET    /api/reservations/availability?date=2026-04-05   — Slots disponibles para una fecha
POST   /api/reservations                                 — Crear
GET    /api/reservations                                 — Listar (filtros: date, status)
PATCH  /api/reservations/{id}/status                     — Cambiar status
GET    /api/reservation-settings                         — Config actual
PUT    /api/reservation-settings                         — Actualizar config + horarios
```

#### 2.3.5 UI

**`/settings/reservations`:**
- Toggle activar/desactivar reservas
- Config: capacidad, tamaño máximo de grupo, días de anticipación, duración de slot
- Grilla de horarios por día de semana (checkboxes + inputs de hora)

**`/reservations`:**
- Vista calendario (semana) con las reservas como bloques
- Vista lista con filtros por fecha y status
- Click abre detalle con botones de acción (confirmar, cancelar, marcar no-show)

**Panel lateral en inbox:**
- Botón "Crear reserva" pre-llenando el contacto
- Historial de reservas del contacto

**Dashboard widget:**
- "Reservas hoy": contador con desglose por status

### 2.4 Programa de fidelización simple

**Concepto:** El restaurante puede marcar visitas de un contacto. Después de N visitas, el sistema sugiere enviar un mensaje de reward.

> **Scope:** Este módulo es **opt-in por vertical**. Solo tiene sentido cuando existen pedidos o reservas (vertical restaurante). No agregar `visit_count` como campo genérico en todos los contactos — activarlo solo cuando el tenant tiene el módulo de restaurante habilitado.

#### 2.4.1 Implementación

Agregar columna `visit_count` (int unsigned default 0) y `last_visit_at` (timestamp nullable) a la tabla `contacts`. Estas columnas existen en el esquema pero solo se usan en el contexto del vertical restaurante — no exponer en la UI general de contactos para tenants sin ese vertical.

Agregar campo `loyalty_threshold` (int unsigned default 5) a `reservation_settings` para que cada tenant configure su umbral.

#### 2.4.2 Flujo

1. Cuando un pedido pasa a status `delivered` o una reserva pasa a `completed`, incrementar `visit_count` y actualizar `last_visit_at`
2. Cuando `visit_count` alcanza un múltiplo de `loyalty_threshold`, crear una tarea automática: "Enviar recompensa a [nombre] — visita #[N]"
3. El agente decide qué enviar (descuento, postre gratis, etc.)

#### 2.4.3 UI

- En la ficha del contacto (solo si el tenant tiene vertical restaurante): mostrar badge "Cliente frecuente — X visitas"
- En `/settings/reservations`: agregar campo "Umbral de fidelización" (input numérico, default 5) junto a la config de reservas — no crear una sección `/settings/loyalty` separada en MVP

---

## 3. ONBOARDING SELF-SERVICE

> **Prioridad:** ALTA — sin esto no puedes vender sin estar presente.

### 3.1 Flujo de registro

```
Landing page (aricrm.co) → Click "Empezar gratis"
→ /register (nombre, email, password, nombre del negocio, tipo: restaurante|tienda|servicios|otro)
→ Email de verificación
→ /onboarding/step-1: Conectar WhatsApp (QR scan)
→ /onboarding/step-2: Configurar perfil del negocio (nombre, logo, timezone, moneda)
→ /onboarding/step-3: Invitar equipo (opcional, skip)
→ /onboarding/step-4: Tour guiado de 4 tooltips (inbox, contactos, pipeline, configuración)
→ Dashboard
```

### 3.2 Implementación

> **Importante:** Ya existe un onboarding wizard implementado en Phase 8. Antes de desarrollar, revisar el estado actual en las rutas `/onboarding/*` y el middleware existente. La tarea aquí es **extender** lo que existe, no reemplazarlo.

Cambios necesarios sobre el onboarding existente:
- Agregar campo `business_type` (enum: `restaurant|store|services|other`) al registro y al modelo `Tenant`
- Extender step-2 (perfil del negocio) con el selector de tipo de negocio
- Si `business_type = restaurant`: activar automáticamente los módulos de menú, pedidos, reservas al completar onboarding (crear `reservation_settings` con defaults)
- El wizard de QR y el `EnsureOnboardingComplete` middleware probablemente ya existen — verificar antes de crear

Campos a verificar/agregar en `tenants`:
- `business_type` enum — nuevo
- `onboarding_completed_at` — verificar si ya existe

### 3.3 Trial

- 14 días de trial sin tarjeta de crédito
- Mostrar banner persistente con días restantes después del día 7
- Al vencer: la cuenta entra en modo read-only (puede ver pero no enviar mensajes ni crear registros)
- CTA a `/billing` para elegir plan

---

## 4. CONFIGURACIÓN STRIPE (PRODUCCIÓN)

> **Prioridad:** BLOCKER — sin esto no se puede cobrar.

### 4.1 Checklist

1. Verificar que `laravel/cashier` está en `composer.json`. Si no: `composer require laravel/cashier`
2. Correr `php artisan cashier:install` si no se ha corrido
3. Crear productos y precios en Stripe Dashboard:
   - Plan "Semilla": $19 USD/mes — 1 agente, 500 contactos, inbox + contactos + menú
   - Plan "Crecer": $29 USD/mes — 3 agentes, 2000 contactos, + pipeline + pedidos + reservas + automatizaciones
   - Plan "Escalar": $59 USD/mes — agentes ilimitados, contactos ilimitados, todo incluido
4. Copiar los Price IDs (`price_xxx`) al `.env`:
   ```
   STRIPE_PRICE_SEED=price_xxx
   STRIPE_PRICE_GROW=price_xxx
   STRIPE_PRICE_SCALE=price_xxx
   ```
5. Configurar webhook de Stripe apuntando a `/stripe/webhook`
6. Verificar que el middleware de límites por plan funciona (ej: plan Semilla bloquea acceso a `/orders` y `/reservations`)

### 4.2 Feature flags por plan

| Feature | Semilla ($19) | Crecer ($29) | Escalar ($59) |
|---|---|---|---|
| Inbox | ✓ | ✓ | ✓ |
| Contactos + tags | ✓ (500 max) | ✓ (2000 max) | ✓ (ilimitados) |
| Menú digital | ✓ | ✓ | ✓ |
| Tareas/recordatorios | ✓ | ✓ | ✓ |
| Pipeline Kanban | — | ✓ | ✓ |
| Pedidos | — | ✓ | ✓ |
| Reservas | — | ✓ | ✓ |
| Automatizaciones | 3 max | ✓ | ✓ |
| Dashboard métricas | básico | completo | completo |
| Agentes | 1 | 3 | ilimitados |
| Fidelización | — | ✓ | ✓ |
| API access | — | — | ✓ |

**Implementación:**
- Gates de Laravel (`Gate::define('use-pipeline', ...)`) consultando el plan del tenant — el plan se lee de `tenant->subscription->stripe_price_id` mapeado a un enum de plan
- Middleware HTTP para rutas completas (ej: bloquear `/orders` si plan es Semilla)
- Helper `$tenant->canUse('feature')` usable en Blade/React props para mostrar/ocultar UI
- Cuando se intenta acceder a feature bloqueada: redirigir o mostrar componente `<UpgradePrompt feature="orders" />` con CTA a `/billing`
- **No usar Spatie Permission para esto** — los plan gates son por tenant, no por usuario; son cosas distintas

---

## 5. MONITORING MÍNIMO

### 5.1 Health check endpoint

```
GET /health → 200 OK si:
  - DB conecta
  - Redis conecta
  - Evolution API responde (GET /instance/fetchInstances)
  - Queue worker está corriendo (verificar con Cache::get('queue:heartbeat'))
```

### 5.2 Queue heartbeat

Crear un job `QueueHeartbeat` que corre cada minuto y hace `Cache::put('queue:heartbeat', now(), 120)`. El health check verifica que ese valor no sea mayor a 3 minutos.

### 5.3 Logging de Evolution API

Evolution API es el componente más frágil del stack — un webhook que falla silencioso es peor que una caída visible.

Agregar al health check:
- Verificar que ningún tenant tenga instancia en estado `disconnected` por más de 10 minutos sin que el owner haya sido notificado
- Loguear en canal `evolution` (Laravel Log) todos los webhooks que lleguen con status != 200 desde Evolution API
- Si un webhook de `connection.update` indica `state: close`, disparar notificación broadcast al owner del tenant afectado

### 5.4 Alertas

Configurar UptimeRobot (o similar gratuito) para hacer ping a `/health` cada 5 minutos. Si falla, enviar alerta al WhatsApp del owner vía un webhook simple.

---

## 6. ORDEN DE EJECUCIÓN

```
Semana 1:  Tareas/recordatorios (sección 1) — completa el MVP core
Semana 2:  Stripe config (sección 4) — BLOCKER real; sin esto no cobras desde día 1
Semana 3:  Menú digital (sección 2.1) — primer feature vertical
Semana 4:  Pedidos (sección 2.2)
Semana 5:  Reservas (sección 2.3) + Fidelización (sección 2.4)
Semana 6:  Onboarding extendido (sección 3) + Monitoring (sección 5)
Semana 7:  Testing integral + primer tenant real (el restaurante)
```

> **Razonamiento del cambio:** Stripe se movió a semana 2 porque si el primer tenant entra en semana 6-7, necesitas tener el sistema de cobro probado con tiempo. Mejor resolver el billing antes de construir features que dependen del gating por plan.

---

## 7. DEFINICIÓN DE TERMINADO (DoD)

Cada feature se considera completa cuando:

- [ ] Migración corre sin errores en PostgreSQL
- [ ] Modelo con relaciones, scopes y casts definidos
- [ ] API endpoints funcionan con autenticación y tenant isolation
- [ ] Policy/Gate implementada (un tenant no ve datos de otro)
- [ ] UI en React/Inertia renderiza correctamente
- [ ] Al menos 3 tests de feature con Pest (happy path, unauthorized, tenant isolation)
- [ ] No hay errores N+1 (verificar con Laravel Debugbar)
- [ ] Real-time funciona donde se indica (Reverb broadcasts)

---

*AriCRM — Vertical Restaurantes MVP — Prompt para Claude Code v1.0*
*Stack: Laravel 11 + React/Inertia + PostgreSQL + Redis + Reverb + Evolution API + S3 + Docker*
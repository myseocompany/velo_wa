# Spec Frontend: Múltiples líneas de WhatsApp por Tenant

> Este documento se implementa después del backend (ver `MULTI_LINE_BACKEND_SPEC.md`).
> Asume que los endpoints API ya existen y funcionan.

## Contexto

Cada tenant ahora puede tener N líneas de WhatsApp. El backend expone:

- `GET /api/v1/whatsapp/lines` — lista de líneas
- `POST /api/v1/whatsapp/lines` — crear línea
- `PATCH /api/v1/whatsapp/lines/{id}` — renombrar / set default
- `DELETE /api/v1/whatsapp/lines/{id}` — eliminar
- `POST /api/v1/whatsapp/lines/{id}/connect` — conectar (retorna QR)
- `POST /api/v1/whatsapp/lines/{id}/disconnect` — desconectar
- `GET /api/v1/whatsapp/lines/{id}/status` — status en vivo
- `GET /api/v1/whatsapp/lines/{id}/health-logs` — health logs
- `GET /api/v1/conversations?whatsapp_line_id=xxx` — filtro por línea
- `POST /api/v1/conversations` — acepta `whatsapp_line_id` opcional

Los endpoints legacy (`/api/v1/whatsapp/connect`, etc.) siguen funcionando y delegan a la línea default.

---

## Cambios requeridos

### 1. Tipos TypeScript

En `resources/js/types/` agregar. El shape debe coincidir con el de `serializeLine()` en `app/Http/Controllers/Api/V1/WhatsAppLineController.php` (actualmente no existe un `WhatsAppLineResource`; si se crea, sincronizar este tipo con él):

```typescript
export type WhatsAppLineStatus = 'disconnected' | 'qr_pending' | 'connected' | 'banned';

export interface WhatsAppLine {
    id: string;
    label: string;
    instance_id: string | null;
    status: WhatsAppLineStatus;
    phone: string | null;
    connected_at: string | null;
    is_default: boolean;
    health_consecutive_failures: number;
    health_last_alert_at: string | null;
    created_at: string;
    updated_at: string;
}
```

Agregar a la interface `Conversation`:
```typescript
whatsapp_line_id: string | null;
whatsapp_line?: WhatsAppLine;
```

Evento de broadcast (canal privado `tenant.{id}`, evento `wa.status.updated`):
```typescript
export interface WaStatusUpdatedPayload {
    line_id?: string;      // presente cuando la actualización es de una línea específica
    label?: string;
    status: WhatsAppLineStatus;
    phone: string | null;
    connected_at: string | null;
    qr_code: string | null; // base64, presente cuando status === 'qr_pending'
    legacy?: boolean;
}
```

---

### 2. Settings/WhatsApp — Gestión multi-línea

**Archivo**: `resources/js/Pages/Settings/WhatsApp.tsx`

Rediseñar de vista de línea única a lista de cards.

**Cada card muestra**:
- **Label** — editable inline (click para editar, Enter para guardar → `PATCH /lines/{id}`). Debe ser accesible por teclado: botón con `aria-label="Renombrar línea"` que activa el modo edición; `Esc` cancela.
- **Phone** — número conectado o "Sin conectar"
- **Status badge** — verde=connected, amarillo=qr_pending, gris=disconnected, rojo=banned. Usar `aria-label` describiendo el estado, no solo color.
- **Botón Connect/Disconnect** — según status actual
- **QR code** — se muestra al hacer connect, en un modal o inline expandible. Debe refrescarse al recibir un evento `wa.status.updated` con `qr_code` no-nulo para esta línea.
- **Toggle default** — radio button, solo uno activo por tenant (`PATCH /lines/{id}` con `is_default: true`). Optimistic update con rollback si falla.
- **Botón eliminar** — con modal de confirmación. Deshabilitado si es la única línea o si el backend responde 409/422 (manejar mensaje de error del server: conversaciones abiertas, default con otras líneas presentes, etc.).
- **Health logs** — sección expandible con últimos checks

**Botón "Agregar línea"**:
- Arriba de la lista
- Disabled + tooltip si se alcanzó el límite del plan
- Al click: modal simple pidiendo el label → `POST /lines`
- Si el backend responde con error de límite de plan (422), mostrar mensaje con CTA a upgrade

**Real-time**:
- Suscribir a `PrivateChannel('tenant.{id}')`, evento `wa.status.updated`
- Filtrar por `line_id` para actualizar el status de la card correcta
- Actualizar QR code cuando llega evento con `qr_code` no-nulo
- Si el evento no trae `line_id` (modo legacy), aplicarlo a la línea default

**Data loading**:
- `GET /api/v1/whatsapp/lines` al montar
- Información del plan (`maxLines`, `currentPlan`) compartida via **props de Inertia** desde el middleware que ya expone el tenant (coherente con el resto del repo). Evita un round-trip extra.

**Manejo de errores (general)**:
- `POST /lines` 422 por límite de plan → banner con info del plan y link a billing
- `DELETE /lines/{id}` 409 conversaciones abiertas → mensaje explícito
- `DELETE /lines/{id}` 409 es default con otras líneas → pedir elegir nueva default primero
- Cambio de default / rename: optimistic UI, rollback visible con toast si la request falla

---

### 3. Inbox — Filtro por línea

**Archivo**: `resources/js/Pages/Inbox/Index.tsx`

**Filtro** (visible si el tenant tiene **>1 línea creada**, independiente de su status — el agente quiere poder filtrar aunque una línea esté caída):
- Dropdown/select arriba de la lista de conversaciones
- Opciones: "Todas las líneas" + una opción por línea
- Cada opción muestra: label + últimos 4 dígitos del phone (ej: "Ventas · ...4567"). Si la línea no tiene `phone`, solo el label.
- Indicador visual de status junto al label (dot de color) para que el agente vea de un vistazo qué líneas están arriba
- Al seleccionar, pasar `whatsapp_line_id` como query param a `GET /api/v1/conversations`
- Persistir selección en URL query param para que sea compartible/refreshable

**Badge de línea en cada conversación**:
- Solo visible si tenant tiene >1 línea
- Badge pequeño con el label de la línea (ej: "Ventas")
- Usar un color sutil, no debe competir con el status badge
- **Fallback** si `whatsapp_line_id` es null (conversaciones previas a la migración que no quedaron asociadas): no mostrar badge. No asumir que es la línea default para evitar información engañosa.

---

### 4. Nueva conversación — Selector de línea

**Archivo**: El componente/diálogo donde se crea una conversación outbound.

**Comportamiento**:
- Si tenant tiene 1 sola línea → no mostrar selector (enviar `whatsapp_line_id` implícitamente, o dejar que el backend use la default)
- Si tenant tiene >1 línea conectada → mostrar selector dropdown
- Pre-seleccionar la línea default (si está conectada); si no, la primera `connected`
- Solo listar líneas con `status = 'connected'`
- Enviar `whatsapp_line_id` en el body del `POST /api/v1/conversations`

**Edge case — cero líneas conectadas**:
- Deshabilitar el formulario de creación
- Mostrar mensaje: "No hay líneas de WhatsApp conectadas. Conecta una línea para iniciar conversaciones." con CTA a `Settings/WhatsApp`.

---

### 5. Onboarding

**Archivo**: `resources/js/Pages/Onboarding.tsx`

**Opción simple (recomendada)**: No cambiar nada. El endpoint legacy `POST /api/v1/whatsapp/connect` sigue funcionando y crea la primera línea automáticamente.

**Opción si se quiere modernizar**: Cambiar Step 2 para:
1. Crear línea: `POST /api/v1/whatsapp/lines` con `{ label: 'Principal' }`
2. Conectar: `POST /api/v1/whatsapp/lines/{id}/connect`
3. Escuchar status updates filtrados por el `line_id` retornado

---

### 6. Conversation detail / header

Si el tenant tiene múltiples líneas, **mostrar en el header de la conversación** qué línea se está usando (ej: "via Ventas · +57 300 123 4567"). Esto ayuda al agente a saber desde qué número está respondiendo y evita errores de contexto.

Si `whatsapp_line_id` es null (legacy), omitir el sub-header.

---

### 7. Comunicación del cambio a tenants existentes

En el primer login post-deploy, tenants con una única línea verán su configuración migrada como una sola card en `Settings/WhatsApp`. Para comunicar que ahora pueden agregar más líneas:

- Tooltip/popover de una sola vez (dismissable) sobre el botón "Agregar línea": "Ahora puedes conectar múltiples números de WhatsApp a tu cuenta."
- Persistir el dismiss en localStorage o user preference
- No es bloqueante; puede postergarse si hay prisa por shippear

---

## UX Guidelines

- **Single-line tenants**: La experiencia debe ser idéntica a la actual. No mostrar filtros, selectores, ni badges de línea si solo hay 1 línea.
- **Multi-line tenants**: Los elementos adicionales (filtro, badges, selector) aparecen automáticamente al tener >1 línea.
- **Settings**: Siempre mostrar la lista de líneas, incluso si hay solo 1. El botón "Agregar línea" comunica que la funcionalidad existe.
- **Plan limits**: Comunicar claramente cuántas líneas permite el plan y cómo upgradear.
- **Accesibilidad**: Toda acción por click debe tener alternativa por teclado. Status comunicado por color debe duplicarse en texto o `aria-label`.

---

## Definition of Done

Checklist funcional para QA antes de dar por cerrado el feature:

- [ ] Tenant nuevo sin líneas ve estado vacío con CTA "Agregar línea"
- [ ] Se puede crear una línea y aparece en la lista inmediatamente
- [ ] Al conectar, aparece el QR y el status pasa a `qr_pending` en tiempo real
- [ ] Al escanear el QR, el status pasa a `connected` sin refrescar la página
- [ ] Renombrar una línea inline funciona con click y con teclado
- [ ] Marcar una línea como default desmarca la anterior (un solo default por tenant)
- [ ] Eliminar una línea pide confirmación y bloquea si tiene conversaciones abiertas
- [ ] No se puede eliminar la línea default si hay otras líneas (backend 409 manejado con mensaje claro)
- [ ] Plan limit: al alcanzar el máximo, el botón queda disabled con tooltip explicativo
- [ ] Tenant con 1 línea: inbox idéntico al actual (sin filtro ni badges)
- [ ] Tenant con 2+ líneas: filtro visible en inbox, persiste en URL, recarga conserva selección
- [ ] Badge de línea aparece en cards de conversación para tenants multi-línea
- [ ] Conversaciones legacy (sin `whatsapp_line_id`) no muestran badge erróneo
- [ ] Nueva conversación pre-selecciona la línea default conectada
- [ ] Nueva conversación con 0 líneas conectadas muestra CTA a settings
- [ ] Header de conversación muestra línea en uso cuando hay >1 línea
- [ ] Eventos `wa.status.updated` actualizan sólo la card correcta (filtro por `line_id`)
- [ ] Errores del backend (409/422) se muestran al usuario, no sólo en consola

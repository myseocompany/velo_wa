# Auditoría técnica: asignación de clientes y edición de teléfono

Fecha: 2026-04-03
Repositorio: `velo_wa`
Alcance: flujos de asignación (contactos/conversaciones) y edición de datos de contacto.

## Resumen ejecutivo

Se identificaron **3 hallazgos relevantes**:

1. **[P1 - Alto] No se puede editar el teléfono del contacto** (UI + API bloquean el campo).
2. **[P1 - Alto] La asignación en Inbox no sincroniza el cliente (contacto)**, generando estado inconsistente (“conversación asignada”, “cliente sin asignar”).
3. **[P2 - Medio] El motor de autoasignación incluye roles no agente** (owner/admin), con riesgo de asignaciones no deseadas.

---

## Hallazgos

### 1) [P1 - Alto] No existe edición de teléfono en el flujo de actualización de contacto

**Evidencia**

- El modal de edición de contacto no tiene input de teléfono.
  - `resources/js/Pages/Contacts/Show.tsx:440`
  - `resources/js/Pages/Contacts/Show.tsx:448`
- El payload de guardado no envía `phone`.
  - `resources/js/Pages/Contacts/Show.tsx:164`
- El request backend para update no valida `phone`.
  - `app/Http/Requests/Api/ContactRequest.php:22`
- El controlador de update no persiste `phone`.
  - `app/Http/Controllers/Api/V1/ContactController.php:166`

**Impacto**

- El usuario final no puede corregir números mal digitados.
- Afecta búsqueda, deduplicación y continuidad del historial por número.

**Riesgo operacional**

- Duplicados por variaciones del número.
- Errores en enrutamiento operativo (mensajería/seguimiento).

---

### 2) [P1 - Alto] Inconsistencia entre asignación de conversación y asignación de cliente

**Evidencia**

- Al asignar una conversación, solo se actualiza `conversations.assigned_to`.
  - `app/Actions/Conversations/AssignConversation.php:15`
- No hay actualización correlativa de `contacts.assigned_to` en esa acción.
  - `app/Actions/Conversations/AssignConversation.php:13`
- En creación de conversación, el contacto solo se asigna si estaba vacío; después queda desacoplado.
  - `app/Actions/Conversations/CreateConversation.php:123`

**Impacto**

- Un mismo cliente puede verse “Sin asignar” en Contactos aunque su conversación esté asignada en Inbox.
- Percepción de fallo de asignación (“no se asignan bien los clientes”).

**Riesgo operacional**

- Reportes/segmentación por responsable incorrectos en módulo de contactos.
- Confusión del equipo al operar entre vistas (Inbox vs Contactos).

---

### 3) [P2 - Medio] Pool de autoasignación no restringido a rol agente

**Evidencia**

- El pool del motor de asignación toma `is_active=true` sin filtrar por rol.
  - `app/Services/AssignmentEngineService.php:152`
- Endpoint de miembros para dropdowns también expone todos los roles activos.
  - `app/Http/Controllers/Api/V1/TeamController.php:26`

**Impacto**

- Reglas automáticas pueden terminar asignando conversaciones a owner/admin.
- Puede desbalancear operación y SLAs si los no-agentes no atienden cola.

**Riesgo operacional**

- Carga mal distribuida y conversaciones “estancadas” en usuarios no operativos.

---

## Reproducción rápida

### Caso A: “No puedo editar teléfono”

1. Ir a detalle de contacto (`/contacts/{id}`).
2. Clic en “Editar contacto”.
3. Verificar que no existe campo teléfono.
4. Guardar cambios y comprobar que el número no puede modificarse por esta vía.

### Caso B: “Cliente mal asignado / no asignado”

1. Asignar conversación desde Inbox.
2. Ir al módulo Contactos y revisar el mismo cliente.
3. Validar que la asignación del contacto puede no reflejar la conversación asignada.

---

## Recomendaciones priorizadas

1. **Habilitar edición de `phone` end-to-end**
   - UI: agregar campo teléfono en modal de edición.
   - API: incluir normalización + validación de unicidad por tenant en `ContactRequest` (ignorar contacto actual en update).
   - Persistencia: incluir `phone` en `ContactController@update`.

2. **Definir y aplicar política única de asignación**
   - Opción recomendada: al asignar conversación, sincronizar `contact.assigned_to` (al menos cuando es la conversación activa/única).
   - Alternativa: separar explícitamente conceptos y reflejarlo en UI con labels para evitar expectativa de sincronía.

3. **Restringir autoasignación a roles operativos**
   - Filtrar pool por `role=agent` (o por un set explícito configurable).
   - Revisar que formularios de reglas y dropdowns de asignación sigan la misma regla de elegibilidad.

---

## Nota final

Esta auditoría fue estática (código) y no incluyó ejecución de pruebas automatizadas ni carga de datos reales en entorno de staging/producción.

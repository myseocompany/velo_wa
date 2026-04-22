# Plan: Cálculo preciso de DT1 (Tiempo a Primera Respuesta Humana)

> Fecha: 2026-04-22
> Referencia: fórmula Excel validada en producción (hoja de cálculo análisis conversaciones)

---

## Objetivo

Calcular el tiempo real que tarda el equipo humano en responder la **primera vez** a un cliente que inicia una conversación, descontando:

- Tiempo fuera de horario laboral
- Respuestas automáticas (bots, plantillas)
- Reinicios de conversación por parte del cliente (nuevo intento dentro de la misma conversación)

---

## Definiciones clave

| Término | Definición |
|---|---|
| **Evento de inicio** | Primer mensaje `in` de un cliente después de un período de silencio (≥ umbral de inactividad) o en conversación nueva |
| **Evento de respuesta** | Primer mensaje `out` de un **agente humano** (no automatizado) tras el evento de inicio |
| **Hora inicio laboral** | Si el evento de inicio cae dentro del horario → se toma tal cual. Si cae fuera → se proyecta al próximo inicio de jornada |
| **Hora fin laboral** | Si la respuesta humana cae dentro del horario → se toma tal cual. Si cae fuera → se proyecta al inicio de jornada (llegó antes de abrir) o al cierre (respondió tarde) |
| **DT1** | Minutos laborales entre hora inicio laboral y hora fin laboral |

---

## Componentes del plan

### 1. Plantilla de respuesta automática

**Problema:** WhatsApp Business permite configurar una respuesta automática de bienvenida/fuera-de-horario. Este mensaje sale con `direction = out` pero no es una respuesta humana real → infla DT1.

**Solución:** Reutilizar la tabla `quick_replies` existente agregando un flag `is_auto_reply BOOLEAN DEFAULT FALSE`.
El tenant marca desde la UI de Quick Replies cuál es su plantilla de auto-respuesta. No se crea una nueva tabla ni un campo en `tenants`.

**Cambio en `quick_replies`:**
```sql
ALTER TABLE quick_replies ADD COLUMN is_auto_reply BOOLEAN NOT NULL DEFAULT FALSE;

-- Solo puede haber una auto_reply activa por tenant
CREATE UNIQUE INDEX quick_replies_tenant_auto_reply
    ON quick_replies (tenant_id)
    WHERE is_auto_reply = TRUE;
```

**Al evaluar si un mensaje `out` cuenta como primera respuesta humana, excluirlo si:**
1. `messages.is_automated = true`, O
2. El cuerpo coincide con cualquier quick reply marcada `is_auto_reply = true` del mismo tenant:

```sql
AND NOT EXISTS (
    SELECT 1 FROM quick_replies qr
    WHERE qr.tenant_id = m.tenant_id
      AND qr.is_auto_reply = true
      AND LOWER(TRIM(qr.body)) = LOWER(TRIM(m.body))
)
```

**UI:** En la página `/settings/quick-replies`, agregar un toggle "Usar como auto-respuesta" en el modal de edición. Al activarlo, desactiva el flag en cualquier otra quick reply del tenant (solo una activa).

---

### 2. Detección de eventos de inicio

**Decisión:** Δt1 se mide **una sola vez por contacto** — en su primera conversación únicamente.

- Es una métrica de **adquisición**, no de retención (lado izquierdo del bowtie).
- Para clientes retenidos que vuelven a escribir, la métrica relevante es otra (resolución, CSAT).
- Simplifica el modelo: no hay umbral de inactividad, no hay múltiples intentos.

**Regla:**
```
t_inicio = first_message_at de la primera conversación del contacto
```

```sql
-- Primera conversación del contacto
SELECT MIN(created_at) FROM conversations WHERE contact_id = :cid
```

**Campos eliminados del modelo original:**
- ~~`is_interaction_start` en messages~~ — no necesario
- ~~`inactivity_threshold_minutes` en tenants~~ — no necesario

---

### 3. Búsqueda de primera respuesta humana

Dado `t_inicio`, la primera respuesta humana es el primer mensaje `out` que cumpla:

```sql
SELECT MIN(m.created_at)
FROM messages m
WHERE m.conversation_id = :first_conversation_id
  AND m.direction = 'out'
  AND m.is_automated = false
  AND NOT EXISTS (
      SELECT 1 FROM quick_replies qr
      WHERE qr.tenant_id = m.tenant_id
        AND qr.is_auto_reply = true
        AND LOWER(TRIM(qr.body)) = LOWER(TRIM(m.body))
  )
```

Si no existe → `dt1_minutes_business = NULL` (lead sin respuesta humana).

---

### 4. Proyección de hora inicio a horario laboral

Dado `t_raw` (timestamp UTC del evento de inicio), y el horario laboral del tenant:

```
si t_raw cae dentro de horario laboral:
    t_inicio_lab = t_raw

si t_raw cae DESPUÉS del cierre (mismo día laboral):
    t_inicio_lab = próximo día laboral a las HH_OPEN

si t_raw cae ANTES de la apertura (mismo día laboral o fin de semana):
    t_inicio_lab = ese mismo día laboral (o próximo) a las HH_OPEN
```

Equivalente a la fórmula Excel:
```
=IF(AND(WEEKDAY(t;2)<=5; MOD(t;1)>=T_OPEN; MOD(t;1)<=T_CLOSE);
   t;
   WORKDAY(INT(t); IF(OR(WEEKDAY(t;2)>5; MOD(t;1)>T_CLOSE); 1; 0)) + T_OPEN)
```

---

### 5. Proyección de hora fin a horario laboral

Dado `t_raw` (timestamp UTC de la primera respuesta humana):

```
CASO 1 — t_raw dentro de horario laboral:
    t_fin_lab = t_raw

CASO 2 — t_raw antes de apertura (madrugada / fin de semana):
    → el agente ya respondió fuera de horario, el cliente no esperó ni un minuto laboral
    → Δt1 = 0 min  ✓

CASO 3 — t_raw después del cierre (mismo día laboral):
    t_fin_lab = ese día a HH_CLOSE
    → se trunca: el equipo no respondió dentro del horario disponible
```

**Tabla de casos combinados inicio + fin:**

| t_inicio | t_fin | Δt1 |
|---|---|---|
| Dentro horario | Dentro horario | Minutos laborales entre los dos |
| Dentro horario | Después del cierre | Desde t_inicio hasta HH_CLOSE |
| Fuera horario | Antes de próxima apertura | 0 min |
| Fuera horario | Dentro horario (día siguiente+) | Desde HH_OPEN hasta t_fin |
| Fuera horario | Después del cierre | HH_CLOSE − HH_OPEN (día completo sin respuesta) |

---

### 6. Cálculo de minutos laborales (diferencia)

Casos:

**Caso A — mismo día laboral:**
```
minutos = MAX(0, MIN(T_CLOSE, t_fin_lab) - MAX(T_OPEN, t_inicio_lab)) × 1440
```

**Caso B — días distintos:**
```
minutos =
  (T_CLOSE - MAX(T_OPEN, t_inicio_lab)) × 1440          ← resto del primer día
  + NETWORKDAYS(dia_inicio+1, dia_fin-1) × minutos_por_dia ← días completos intermedios
  + MAX(0, MIN(T_CLOSE, t_fin_lab) - T_OPEN) × 1440     ← fracción del último día
```

Donde `minutos_por_dia = (T_CLOSE - T_OPEN) × 1440` (ej. 8h–18h = 600 min).

Este es exactamente el cálculo de la fórmula Excel `tiempo_respuesta`.

---

### 7. Configuración de timezone y horario laboral

El tenant ya tiene:
- `timezone` (ej. `America/Bogota`, UTC-5) ✅
- `business_hours` JSONB con horario por día ✅ (usado actualmente en DT1 business hours)

**Pendiente:**
- Verificar que `business_hours` distingue días de la semana correctamente (lun–vie vs. fin de semana).
- Agregar campo `inactivity_threshold_minutes INT DEFAULT 1440` en `tenants` para el umbral de reinicio de interacción.

---

### 8. Almacenamiento del DT1 calculado

**Tabla `conversations`** — campos existentes:
- `first_message_at` ✅
- `first_response_at` ✅

**Nuevos campos propuestos:**

```sql
ALTER TABLE conversations ADD COLUMN IF NOT EXISTS
  first_human_response_at TIMESTAMPTZ NULL;   -- timestamp real (no automática)

ALTER TABLE conversations ADD COLUMN IF NOT EXISTS
  dt1_minutes_business INT NULL;              -- DT1 en minutos laborales (el número del dashboard)
```

`first_response_at` existente se mantiene (primer `out` cualquiera).
`first_human_response_at` es el nuevo campo de precisión.

---

## Flujo de procesamiento (webhook)

```
WhatsApp webhook → mensaje IN llega
  ↓
¿Es nuevo INICIO_INTERACCION?
  (primer msg de la conv, o gap > umbral)
  ↓ sí
  Guardar first_message_at en conversation
  Marcar message.is_interaction_start = true
  ↓
WhatsApp webhook → mensaje OUT llega
  ↓
¿is_automated = false Y body ≠ auto_reply_body?
  ↓ sí → es respuesta humana
  ↓
Buscar INICIO_INTERACCION más reciente sin respuesta humana
  ↓
Calcular t_inicio_lab y t_fin_lab (proyección a horario laboral)
  ↓
Calcular dt1_minutes_business
  ↓
Guardar first_human_response_at y dt1_minutes_business en conversation
```

---

## Cambios al dashboard

El DT1 del dashboard actualmente usa:
```sql
EXTRACT(EPOCH FROM (first_response_at - first_message_at))
```

Reemplazar por:
```sql
dt1_minutes_business * 60   -- convertir minutos laborales → segundos para compatibilidad
```

Con fallback a la fórmula actual si `dt1_minutes_business IS NULL` (datos históricos pre-migración).

---

## Migraciones necesarias

| # | Migración | Descripción |
|---|---|---|
| 1 | `add_is_auto_reply_to_quick_replies` | Columna `is_auto_reply BOOLEAN DEFAULT FALSE` + unique index parcial por tenant |
| 2 | `add_dt1_fields_to_conversations` | `first_human_response_at TIMESTAMPTZ NULL` + `dt1_minutes_business INT NULL` |
| 3 | (job) `BackfillDt1BusinessMinutes` | Recalcular primera conversación de cada contacto |

---

## Casos borde a manejar

| Caso | Comportamiento esperado |
|---|---|
| Respuesta llega a las 2am (madrugada) | `t_fin_lab` = próxima apertura. DT1 = 0 min si respuesta antes de abrir |
| Cliente escribe sábado a las 10am | `t_inicio_lab` = lunes 8am |
| Agente responde antes que el cliente (mensaje proactivo) | No cuenta como DT1 |
| Conversación sin respuesta humana | `dt1_minutes_business = NULL` |
| Tenant sin `business_hours` configurado | Fallback: lun–vie 8:00–18:00 |
| Múltiples reinicios en una conversación | Cada uno genera su propio DT1, el dashboard promedia todos |
| Holiday / festivos | Fase 2 — por ahora solo lun–vie |

---

## Fases de implementación

**Fase 1 (MVP):**
- Migraciones 1–4
- Lógica de cálculo en `CalculateDt1BusinessMinutes` Action
- Hook en webhook handler (mensajes OUT humanos)
- Dashboard usa `dt1_minutes_business`

**Fase 2:**
- Job de backfill histórico
- UI para configurar `auto_reply_body` e `inactivity_threshold_minutes` en Settings
- Detección de reinicios (`is_interaction_start`) en tiempo real
- Soporte de festivos por país

---

## Referencias

- Fórmula Excel validada: `hora_inicio`, `hora_fin`, `tiempo_respuesta` (hoja análisis 20/04/2026)
- Horario actual Bogotá: lun–vie 8:00–18:00, UTC-5
- Campo existente `business_hours` en `tenants` (JSONB por día)
- Campo existente `is_automated` en `messages`

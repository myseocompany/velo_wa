# Revenue Architecture & Bowtie — Contexto para AriCRM

## 1. Tesis central

En un negocio de ingresos recurrentes, el embudo clásico (marketing → ventas → cierre) es insuficiente. El cierre no es el final del proceso comercial: es el punto medio. El revenue real se genera **después** del primer contrato, vía retención y expansión.

El **Bowtie** (Jacco van der Kooij, Winning by Design) es el modelo que extiende el embudo hacia post-venta, tratando todo el ciclo de vida del cliente como un sistema unificado de generación de ARR.

> "No recurring impact. No recurring revenue."

---

## 2. Los 3 componentes del ARR

Todo crecimiento de ingresos recurrentes proviene de exactamente tres fuentes:

| Componente | Naturaleza | Crecimiento | Driver |
|---|---|---|---|
| **Adquisición** | Nuevos clientes cerrados | Lineal | Ventas |
| **Retención** | ARR que se mantiene | Exponencial | Producto + CS |
| **Expansión** | Upsell / cross-sell | Exponencial | CS + Producto |

**Regla clave:** a partir del año 5, la retención eclipsa a la adquisición en la mayoría de SaaS maduros. Un CRM que solo mide adquisición está ciego al 70%+ del revenue futuro.

---

## 3. Fases de crecimiento (contexto del cliente)

| Fase | ARR | Foco |
|---|---|---|
| **Startup** | PMF → $20M | Founder-led sales, validar PMF |
| **Scaleup** | $20M → $100M | Escalar GTM motion |
| **Grownup** | $100M+ | Eficiencia, IPO |

AriCRM apunta a micro-empresas y pymes LATAM: la mayoría estará en **pre-Startup** (pre-PMF) o Startup temprano. El bowtie es contraintuitivamente **más valioso aquí**, porque estos negocios típicamente no tienen ningún instrumento para medir retención/expansión.

---

## 4. El Bowtie — estructura

```
ADQUISICIÓN (Dominio de Valor)    |    RETENCIÓN Y EXPANSIÓN (Dominio de Impacto)
                                   |
Conciencia → Educación → Selección → COMMIT → Onboarding → Retención → Expansión
    VM1        VM2         VM3        VM4      VM5/VM6      VM7/VM8      VM9
```

**7 etapas del customer journey:**

1. **Conciencia** — el prospecto descubre que tiene un problema
2. **Educación** — entiende opciones de solución
3. **Selección** — evalúa y elige proveedor
4. **Commit** — firma / paga (nudo central del bowtie)
5. **Onboarding** — llega al primer valor (time-to-value)
6. **Retención** — renueva el contrato
7. **Expansión** — compra más (upsell / cross-sell / referral)

---

## 5. Modelo de datos estándar — 3 familias de métricas

Para cada etapa del bowtie, se miden **3 tipos de métricas**:

### 5.1 Volume Metrics (VM) — "¿Cuántos?"

| Métrica | Descripción | Nombre común |
|---|---|---|
| VM1 | Coincide con perfil objetivo | Prospect / ICP |
| VM2 | Expresa interés, da contacto | MQL |
| VM3 | Siente dolor suficiente para actuar | SQL |
| VM4 | Prioridad verificada | Opp calificada / SAL |
| VM5 | Compromisos mutuos cerrados | Wins / Closed Won |
| VM6 | Revenue comprometido | MRR/ARR comprometido |
| VM7 | Revenue post-onboarding churn | MRR start |
| VM8 | Revenue recurrente activo | MRR / ARR |
| VM9 | Revenue total del cliente | LTV |

### 5.2 Conversion Rates (CR) — "¿Qué tan eficiente?"

| Métrica | Etapa | Definición |
|---|---|---|
| CR1 | Conciencia | Prospect → Lead |
| CR2 | Educación | Lead → Opp |
| CR3 | Selección | Opp → Calificada |
| CR4 | Venta | **Win rate** |
| CR5 | Commit | Revenue comprometido (lista – descuento) |
| CR6 | Onboarding | 1 – onboarding churn |
| CR7 | Retención | **GRR (Gross Revenue Retention)** |
| CR8 | Expansión | Nuevo ARR de upsell/cross-sell |

### 5.3 Time Metrics (Δt) — "¿Qué tan rápido?"

| Métrica | Etapa | Definición |
|---|---|---|
| Δt1 | Conciencia | Tiempo hasta primera conversación |
| Δt3 | Priorización | Tiempo para calificar |
| Δt4 | Venta | Duración del ciclo de ventas |
| Δt6 | Onboarding | **Time-to-value** |
| Δt7 | Retención | Duración del contrato |
| Δt8 | Expansión | Lifetime del cliente (años) |

---

## 6. Benchmarks por ACV (Annual Contract Value)

Tabla de referencia SaaS. El CRM debe permitir configurar ACV para autocomparar contra benchmark:

| ACV | CR1 | CR2 | CR3 | CR4 (Win) | CR6 | CR7 (GRR) | CR8 (Expansión) |
|---|---|---|---|---|---|---|---|
| ≤ $1k | 5% | 10% | 65% | 15% | 90% | 90% | 5% |
| ≤ $5k | 7% | 12% | 70% | 17% | 92% | 92% | 10% |
| ≤ $15k | 8% | 15% | 80% | 20% | 93% | 95% | 15% |
| ≤ $50k | 9% | 18% | 90% | 25% | 94% | 96% | 20% |
| ≤ $150k | 10% | 20% | 95% | 30% | 98% | 97% | 25% |
| > $150k | — | — | 100% | 35% | 99% | 98% | 30% |

**Nota LATAM micro-pyme:** la mayoría de clientes de AriCRM caerán en ACV ≤ $1k o ≤ $5k. Los benchmarks de CR7 y CR8 son referencia aspiracional; el ACV bajo típicamente trae GRR más volátil.

---

## 7. Las 2 fórmulas matemáticas del Bowtie

### 7.1 Adquisición = PRODUCTO (π)

```
Commits = VM1 × CR1 × CR2 × CR3 × CR4
ARR_nuevo = Commits × ACV
```

**Implicación de diseño:** como es producto, una mejora del 5% en una sola tasa intermedia (ej. CR4 de 85% a 90%) puede **casi duplicar** los cierres. Impacto no lineal. El CRM debe permitir **simular** el efecto de mover cada palanca.

### 7.2 Retención/Expansión = SUMA (Σ)

```
LTV = Σ ARR(t), t = 1 a lifetime
```

Con GRR 90% y NRR 110%, el LTV a 5 años es dramáticamente distinto al de GRR 80%. El CRM debe proyectar cohortes en el tiempo, no solo medir el snapshot actual.

---

## 8. Estrategia de monetización — arco

```
OWNERSHIP ←—————— SUSCRIPCIÓN ——————→ CONSUMPTION
(valor)            (balance)            (impacto)
```

| Métrica | Ownership | Susc. Anual | Susc. Mensual | Consumption |
|---|---|---|---|---|
| Precio año 1 | $M+ | $10k+ | $1k+ | $100+ |
| Win rate | 33% | 20% | 17% | 10% |
| Ciclo ventas | 9–18m | 30d+ | 10d+ | 1d+ |
| Retención | 100% | >90% | >78% | Variable |
| Pipeline | 3x | 5x | 6x | 10x |

**Error crítico que el CRM debe ayudar a evitar:** operar con métricas de un modelo de monetización distinto al real. Ej: vender suscripción mensual con pipeline 3x (cuando debería ser 6x).

---

## 9. Principios de diseño para AriCRM

### 9.1 Process-centric, no people-centric

El CRM no debe ser un repositorio pasivo de contactos. Debe **inspeccionar el proceso** y señalar dónde se rompe. La pregunta no es "¿qué tareas tienen mis vendedores?", es "¿en qué etapa del bowtie se está fugando el ARR?"

### 9.2 El bowtie es la entidad central del modelo de datos

Todo contacto, deal, conversación y evento debe poder ubicarse en una de las **7 etapas** del bowtie. Esto no es un tag opcional — es la columna vertebral del schema.

### 9.3 Diagnóstico por bottleneck, no por volumen

El CRM debe identificar automáticamente la **etapa con peor CR o mayor Δt vs benchmark** y señalarla como el cuello de botella. Meter más leads a un funnel con CR4 roto solo amplifica la fuga.

### 9.4 Closed loops (bucles de retroalimentación)

El revenue no es lineal. El CRM debe permitir **modelar bucles**: advocacy (clientes existentes que postean), referrals, reactivación de churned. Cada loop tiene origen, destino y responsable.

### 9.5 Matriz 2x2 como patrón de acción

Dos métricas cruzadas generan 4 cuadrantes → 4 acciones distintas. Ej: Δt6 (time-to-value) vs CR6 (onboarding churn). El CRM debe tener este patrón como visualización nativa.

---

## 10. Diferenciador de AriCRM vs. CRMs tradicionales

| Capacidad | CRMs tradicionales (HubSpot, Pipedrive, etc.) | AriCRM |
|---|---|---|
| Pipeline de adquisición | ✅ | ✅ |
| Conversaciones WhatsApp | Parcial | ✅ nativo |
| **Post-venta como parte del modelo** | ❌ (módulo separado) | ✅ bowtie unificado |
| **Métricas VM/CR/Δt automáticas** | ❌ | ✅ |
| **Benchmark por ACV** | ❌ | ✅ |
| **Diagnóstico de bottleneck** | ❌ | ✅ |
| **Proyección LTV por cohorte** | ❌ / enterprise only | ✅ |
| **Simulador de palancas (+10%)** | ❌ | ✅ |

**Tesis de posicionamiento:** AriCRM es el primer CRM WhatsApp-first LATAM que trata el bowtie como modelo de datos nativo, no como reporte adicional. No solo mide "¿cuántos dijeron sí?" — mide **por qué**, **dónde se fugan**, y **qué palanca mover primero**.

---

## 11. MVP de la capa Bowtie en AriCRM — prioridades

**V1 (mínimo para diferenciación):**

1. Schema: toda entidad deal/contacto tiene `bowtie_stage` (enum de 7 valores)
2. Dashboard: VM1–VM8 automáticas desde conversaciones e eventos
3. Cálculo de CR1–CR8 en tiempo real
4. Configuración de ACV del cliente → carga de benchmark automático
5. Semáforo: métrica en rango / fuera de rango

**V1.1:**

6. Δt automáticas (timestamps entre cambios de stage)
7. Identificador de bottleneck (peor desviación vs benchmark)
8. Proyección LTV simple (GRR fijo × cohorte)

**V2:**

9. Simulador de palancas (+10% en cada CR → impacto en ARR)
10. Closed loops configurables
11. Matriz 2x2 configurable
12. Cohortes dinámicas con NRR

---

## 12. Glosario operativo

- **ARR** — Annual Recurring Revenue
- **MRR** — Monthly Recurring Revenue
- **ACV** — Annual Contract Value (precio promedio anual por deal)
- **LTV** — Lifetime Value (revenue total de un cliente en toda su vida)
- **GRR** — Gross Revenue Retention (retención sin contar expansión)
- **NRR** — Net Revenue Retention (incluye expansión; puede ser >100%)
- **ICP** — Ideal Customer Profile
- **MQL / SQL / SAL** — Marketing/Sales/Sales-Accepted Qualified Lead
- **GTM motion** — forma específica de ir al mercado (ej: inbound PLG, outbound SDR, channel)
- **Time-to-value** — tiempo desde firma hasta primer impacto real en el cliente
- **Δt** — delta de tiempo entre dos etapas del bowtie

---

¿Lo exportas a un `.md` físico o lo quieres así para pegar en docs del repo?
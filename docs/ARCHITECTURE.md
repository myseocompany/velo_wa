# System Architecture

## High-Level Overview

```
┌──────────────────────────────────────────────────────────┐
│                    BROWSER (React/Inertia)                │
│  Inbox │ Pipeline │ Contacts │ Dashboard │ Settings       │
└────────────┬──────────────────────┬──────────────────────┘
             │ HTTP (Inertia)       │ WebSocket (Reverb)
             ▼                      ▼
┌──────────────────────────────────────────────────────────┐
│                   VELO CORE (Laravel 11)                  │
│                                                           │
│  ┌─────────┐ ┌────────────┐ ┌──────────┐ ┌───────────┐  │
│  │  Auth &  │ │  Contact & │ │ Pipeline │ │  Metrics  │  │
│  │ Tenancy  │ │   Convo    │ │ & Deals  │ │  Engine   │  │
│  └─────────┘ └────────────┘ └──────────┘ └───────────┘  │
│  ┌─────────┐ ┌────────────┐ ┌──────────┐ ┌───────────┐  │
│  │ Webhook │ │ Assignment │ │ Message  │ │   Media   │  │
│  │ Handler │ │   Engine   │ │ Service  │ │  Service  │  │
│  └────┬────┘ └────────────┘ └────┬─────┘ └─────┬─────┘  │
│       │                          │              │         │
│  ┌────▼──────────────────────────▼──────────────▼─────┐  │
│  │            Laravel Horizon (Queue Worker)           │  │
│  └────────────────────────┬───────────────────────────┘  │
└───────────────────────────┼──────────────────────────────┘
                            │
        ┌───────────────────┼───────────────────┐
        ▼                   ▼                   ▼
┌──────────────┐  ┌─────────────────┐  ┌──────────────┐
│  PostgreSQL  │  │      Redis      │  │  S3 / MinIO  │
│  (Data)      │  │ (Cache/Queue/   │  │  (Media)     │
│              │  │  Sessions)      │  │              │
└──────────────┘  └─────────────────┘  └──────────────┘
                            │
                            ▼
              ┌─────────────────────────┐
              │  WhatsApp Service       │
              │  (Evolution API/Docker) │
              │                         │
              │  Instance per tenant    │
              │  QR ←→ Session mgmt    │
              │  Send/Receive msgs      │
              └─────────────────────────┘
                            │
                            ▼
                    WhatsApp Network
```

---

## Data Flow: Message Lifecycle

### Inbound Message (Lead → Velo)

```
WhatsApp Network
    │
    ▼
Evolution API (receives via Baileys protocol)
    │
    ▼ HTTP POST webhook
Laravel Webhook Controller
    │
    ├─→ Validate payload & identify tenant (by instance_id)
    ├─→ Find or create Contact (by wa_id)
    ├─→ Find or create Conversation
    ├─→ Create Message record (direction: in)
    ├─→ Apply assignment rules (if unassigned conversation)
    ├─→ Calculate Dt1 (if first message in conversation)
    ├─→ Broadcast MessageReceived event → Reverb
    │
    ▼
Frontend (React) receives via Echo
    │
    └─→ Update inbox in real-time
```

### Outbound Message (Agent → Lead)

```
Agent types in Inbox
    │
    ▼ HTTP POST /api/v1/messages
Laravel MessageController
    │
    ├─→ Validate & authorize (tenant + conversation ownership)
    ├─→ Create Message record (direction: out, status: pending)
    ├─→ Dispatch SendWhatsAppMessage job → high queue
    ├─→ Broadcast MessageSent event → Reverb (optimistic UI)
    │
    ▼
Horizon processes job
    │
    ├─→ POST to Evolution API /message/sendText
    ├─→ On success: update status → sent
    ├─→ On failure: retry (3x) → mark failed
    │
    ▼
Evolution API sends via Baileys
    │
    ▼
WhatsApp Network
    │
    ▼ Delivery receipt
Evolution API → webhook → Laravel
    │
    └─→ Update message status: delivered → read
        └─→ Broadcast MessageStatusUpdated → Reverb
```

### QR Connection Flow

```
Tenant Admin clicks "Connect WhatsApp"
    │
    ▼ HTTP POST /api/v1/whatsapp/connect
Laravel WhatsAppController
    │
    ├─→ POST to Evolution API /instance/create (if new)
    ├─→ GET /instance/connect/{name} → returns QR base64
    ├─→ Return QR to frontend
    │
    ▼
Frontend displays QR code
    │
    ▼ User scans with WhatsApp
Evolution API detects connection
    │
    ▼ Webhook: connection.update (status: open)
Laravel WebhookHandler
    │
    ├─→ Update tenant: wa_status = connected, wa_phone = number
    ├─→ Broadcast InstanceStatusChanged → Reverb
    │
    ▼
Frontend updates: "Connected ✓"
```

---

## Multi-tenancy Architecture

### Tenant Resolution

```
Request → TenantMiddleware
    │
    ├─ Web routes: resolved from authenticated user's tenant_id
    ├─ API routes: resolved from authenticated user's tenant_id (Sanctum)
    ├─ Webhook routes: resolved from instance_id in payload
    │
    ▼
TenantContext::set($tenant) → available globally for the request
    │
    └─→ All Eloquent models with HasTenant trait auto-scope queries
```

### Data Isolation Strategy

```
1. Every tenant-scoped table has: tenant_id UUID NOT NULL, INDEX
2. Global scope on models: WHERE tenant_id = current_tenant
3. Middleware validates tenant on every request
4. Foreign keys reference within same tenant (enforced at app level)
5. API resources never expose tenant_id to frontend
```

---

## Assignment Engine

```
New unassigned conversation arrives
    │
    ▼
AssignmentService::assign($conversation)
    │
    ├─→ Load tenant's assignment rules (ordered by priority)
    │
    ├─→ Rule: Round Robin
    │   └─→ Get online agents → pick next in rotation
    │
    ├─→ Rule: Least Busy
    │   └─→ Count open conversations per agent → pick lowest
    │
    ├─→ Rule: Tag-based
    │   └─→ Match contact tags to agent specialties
    │
    ├─→ Rule: Manual (no auto-assign)
    │   └─→ Leave unassigned, notify all agents
    │
    ▼
Assign conversation → Broadcast ConversationAssigned
```

---

## Metrics: Bowtie Pipeline

```
                    ┌─── ACQUIRE ───┐  ┌─── EXPAND ───┐
                    │               │  │               │
    Lead → Qualified → Proposal → Close Won → Upsell → Advocate
                                  │
                              Close Lost

Each stage transition is timestamped:
  - Time in stage = stage_entered_at - previous_stage_entered_at
  - Conversion rate = deals_moved_forward / deals_in_stage
  - Dt1 = first_response_at - conversation.created_at
```

---

## Security Layers

```
┌─────────────────────────────────────────┐
│ 1. HTTPS everywhere (TLS termination)   │
├─────────────────────────────────────────┤
│ 2. Laravel Sanctum (SPA auth + tokens)  │
├─────────────────────────────────────────┤
│ 3. Tenant middleware (isolation)         │
├─────────────────────────────────────────┤
│ 4. RBAC: owner > admin > agent          │
├─────────────────────────────────────────┤
│ 5. Rate limiting (per user + per tenant) │
├─────────────────────────────────────────┤
│ 6. Webhook signature validation          │
├─────────────────────────────────────────┤
│ 7. S3 pre-signed URLs (no public media)  │
├─────────────────────────────────────────┤
│ 8. Input sanitization (XSS prevention)   │
└─────────────────────────────────────────┘
```

---

## Scaling Strategy

### Phase 1: Single Server (0-50 tenants)

- Docker Compose on single VPS (4 CPU, 8GB RAM)
- All services colocated
- Single PostgreSQL instance
- Adequate for MVP and early traction

### Phase 2: Service Separation (50-500 tenants)

- Separate VPS for Evolution API (WA sessions are memory-heavy)
- Managed PostgreSQL (RDS or equivalent)
- Managed Redis (ElastiCache or equivalent)
- Laravel on dedicated compute
- S3 for media

### Phase 3: Horizontal Scale (500+ tenants)

- Multiple Evolution API nodes with instance routing
- Laravel behind load balancer (stateless)
- Read replicas for PostgreSQL
- Queue workers auto-scaled by queue depth
- CDN for media delivery
- Consider migration to Baileys direct for resource optimization

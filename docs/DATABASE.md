# Database Schema

## Overview

Single PostgreSQL database, multi-tenant via `tenant_id` on all scoped tables. UUIDs for all primary keys. Soft deletes where noted.

---

## Entity Relationship Diagram

```
tenants
  │
  ├──< users (belongs to tenant, has role)
  │      │
  │      └──< messages.sent_by
  │
  ├──< contacts (wa_id unique per tenant)
  │      │
  │      ├──< conversations
  │      │      │
  │      │      └──< messages
  │      │
  │      └──< pipeline_deals
  │
  ├──< assignment_rules
  │
  ├──< quick_replies
  │
  └──< automations
```

---

## Tables

### tenants

Core tenant/organization table.

```sql
CREATE TABLE tenants (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name            VARCHAR(255) NOT NULL,
    slug            VARCHAR(100) NOT NULL UNIQUE,
    plan            VARCHAR(50) NOT NULL DEFAULT 'free',  -- free, starter, pro, enterprise

    -- WhatsApp connection
    wa_instance_id  VARCHAR(255) UNIQUE,          -- Evolution API instance name
    wa_status       VARCHAR(50) DEFAULT 'disconnected',  -- disconnected, qr_pending, connected, banned
    wa_phone        VARCHAR(20),                  -- Connected phone number
    wa_connected_at TIMESTAMP,

    -- Limits (based on plan)
    max_agents      INTEGER NOT NULL DEFAULT 2,
    max_contacts    INTEGER NOT NULL DEFAULT 500,
    media_retention_days INTEGER NOT NULL DEFAULT 30,

    -- Settings
    timezone        VARCHAR(50) DEFAULT 'America/Bogota',
    business_hours  JSONB,  -- { mon: { start: "08:00", end: "18:00" }, ... }
    auto_close_hours INTEGER DEFAULT 24,  -- Auto-close conversations after X hours of inactivity

    created_at      TIMESTAMP NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMP NOT NULL DEFAULT NOW(),
    deleted_at      TIMESTAMP  -- soft delete
);

CREATE INDEX idx_tenants_slug ON tenants(slug);
CREATE INDEX idx_tenants_wa_instance ON tenants(wa_instance_id);
```

### users

Tenant members (owners, admins, agents).

```sql
CREATE TABLE users (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id       UUID NOT NULL REFERENCES tenants(id),

    name            VARCHAR(255) NOT NULL,
    email           VARCHAR(255) NOT NULL,
    password        VARCHAR(255) NOT NULL,
    role            VARCHAR(50) NOT NULL DEFAULT 'agent',  -- owner, admin, agent

    is_active       BOOLEAN NOT NULL DEFAULT true,
    is_online       BOOLEAN NOT NULL DEFAULT false,
    last_seen_at    TIMESTAMP,

    -- Agent-specific
    max_concurrent_conversations INTEGER DEFAULT 10,
    specialties     JSONB DEFAULT '[]',  -- ["ventas", "soporte", "cobranza"]

    email_verified_at TIMESTAMP,
    remember_token  VARCHAR(100),
    created_at      TIMESTAMP NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMP NOT NULL DEFAULT NOW(),
    deleted_at      TIMESTAMP,

    UNIQUE(tenant_id, email)
);

CREATE INDEX idx_users_tenant ON users(tenant_id);
CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_users_role ON users(tenant_id, role);
```

### contacts

WhatsApp contacts / leads.

```sql
CREATE TABLE contacts (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id       UUID NOT NULL REFERENCES tenants(id),

    wa_id           VARCHAR(50) NOT NULL,  -- 573001234567@s.whatsapp.net
    phone           VARCHAR(20) NOT NULL,  -- 573001234567
    name            VARCHAR(255),          -- From WA profile or manually set
    push_name       VARCHAR(255),          -- WhatsApp push name (auto)
    profile_pic_url VARCHAR(500),

    email           VARCHAR(255),
    company         VARCHAR(255),
    notes           TEXT,

    tags            JSONB DEFAULT '[]',    -- ["vip", "bogota", "referido"]
    custom_fields   JSONB DEFAULT '{}',    -- Tenant-defined custom fields

    assigned_to     UUID REFERENCES users(id),
    source          VARCHAR(50) DEFAULT 'whatsapp',  -- whatsapp, manual, import

    first_contact_at TIMESTAMP,            -- First time they messaged
    last_contact_at  TIMESTAMP,            -- Last message from them

    created_at      TIMESTAMP NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMP NOT NULL DEFAULT NOW(),
    deleted_at      TIMESTAMP,

    UNIQUE(tenant_id, wa_id)
);

CREATE INDEX idx_contacts_tenant ON contacts(tenant_id);
CREATE INDEX idx_contacts_wa_id ON contacts(tenant_id, wa_id);
CREATE INDEX idx_contacts_assigned ON contacts(tenant_id, assigned_to);
CREATE INDEX idx_contacts_phone ON contacts(tenant_id, phone);
CREATE INDEX idx_contacts_tags ON contacts USING GIN(tags);
```

### conversations

A conversation thread between a contact and the tenant.

```sql
CREATE TABLE conversations (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id       UUID NOT NULL REFERENCES tenants(id),
    contact_id      UUID NOT NULL REFERENCES contacts(id),

    status          VARCHAR(50) NOT NULL DEFAULT 'open',  -- open, pending, closed
    channel         VARCHAR(50) NOT NULL DEFAULT 'whatsapp',  -- whatsapp, manual

    assigned_to     UUID REFERENCES users(id),
    assigned_at     TIMESTAMP,

    -- Metrics
    first_message_at    TIMESTAMP,  -- First inbound message
    first_response_at   TIMESTAMP,  -- First outbound message (for Dt1)
    last_message_at     TIMESTAMP,  -- Last message of any direction
    message_count       INTEGER NOT NULL DEFAULT 0,

    -- Auto-management
    closed_at           TIMESTAMP,
    closed_by           UUID REFERENCES users(id),  -- null = auto-closed
    reopen_count        INTEGER NOT NULL DEFAULT 0,

    created_at      TIMESTAMP NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMP NOT NULL DEFAULT NOW(),

    -- No soft delete: conversations are closed, not deleted
    CONSTRAINT fk_conversation_tenant CHECK (tenant_id IS NOT NULL)
);

CREATE INDEX idx_conversations_tenant ON conversations(tenant_id);
CREATE INDEX idx_conversations_contact ON conversations(tenant_id, contact_id);
CREATE INDEX idx_conversations_assigned ON conversations(tenant_id, assigned_to);
CREATE INDEX idx_conversations_status ON conversations(tenant_id, status);
CREATE INDEX idx_conversations_last_msg ON conversations(tenant_id, last_message_at DESC);
```

### messages

Individual messages within conversations.

```sql
CREATE TABLE messages (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    conversation_id UUID NOT NULL REFERENCES conversations(id),
    tenant_id       UUID NOT NULL REFERENCES tenants(id),  -- Denormalized for query performance

    direction       VARCHAR(10) NOT NULL,  -- in, out

    -- Content
    body            TEXT,
    media_url       VARCHAR(500),     -- S3 pre-signed URL
    media_type      VARCHAR(50),      -- image, audio, video, document, sticker
    media_mime_type VARCHAR(100),
    media_filename  VARCHAR(255),

    -- Delivery tracking
    status          VARCHAR(50) NOT NULL DEFAULT 'pending',  -- pending, sent, delivered, read, failed
    wa_message_id   VARCHAR(100),     -- WhatsApp's message ID for dedup
    error_message   VARCHAR(500),     -- If status = failed

    -- Sender
    sent_by         UUID REFERENCES users(id),  -- NULL for inbound messages
    is_automated    BOOLEAN NOT NULL DEFAULT false,  -- Chatbot/auto-reply

    created_at      TIMESTAMP NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMP NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_messages_conversation ON messages(conversation_id, created_at);
CREATE INDEX idx_messages_tenant ON messages(tenant_id);
CREATE INDEX idx_messages_wa_id ON messages(wa_message_id);
CREATE INDEX idx_messages_status ON messages(conversation_id, status);
```

### pipeline_deals

Sales pipeline deals (bowtie model).

```sql
CREATE TABLE pipeline_deals (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id       UUID NOT NULL REFERENCES tenants(id),
    contact_id      UUID NOT NULL REFERENCES contacts(id),
    conversation_id UUID REFERENCES conversations(id),

    title           VARCHAR(255) NOT NULL,
    stage           VARCHAR(50) NOT NULL DEFAULT 'lead',
        -- lead, qualified, proposal, negotiation, closed_won, closed_lost

    value           DECIMAL(12, 2),       -- Deal monetary value
    currency        VARCHAR(3) DEFAULT 'COP',

    -- Stage timestamps (for bowtie metrics)
    lead_at         TIMESTAMP,
    qualified_at    TIMESTAMP,
    proposal_at     TIMESTAMP,
    negotiation_at  TIMESTAMP,
    closed_at       TIMESTAMP,

    lost_reason     VARCHAR(255),         -- If closed_lost
    won_product     VARCHAR(255),         -- If closed_won

    assigned_to     UUID REFERENCES users(id),
    notes           TEXT,

    created_at      TIMESTAMP NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMP NOT NULL DEFAULT NOW(),
    deleted_at      TIMESTAMP
);

CREATE INDEX idx_deals_tenant ON pipeline_deals(tenant_id);
CREATE INDEX idx_deals_contact ON pipeline_deals(tenant_id, contact_id);
CREATE INDEX idx_deals_stage ON pipeline_deals(tenant_id, stage);
CREATE INDEX idx_deals_assigned ON pipeline_deals(tenant_id, assigned_to);
```

### assignment_rules

Per-tenant rules for auto-assigning conversations.

```sql
CREATE TABLE assignment_rules (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id       UUID NOT NULL REFERENCES tenants(id),

    name            VARCHAR(255) NOT NULL,
    type            VARCHAR(50) NOT NULL,  -- round_robin, least_busy, tag_based, manual
    priority        INTEGER NOT NULL DEFAULT 0,  -- Lower = higher priority
    is_active       BOOLEAN NOT NULL DEFAULT true,

    -- Configuration (varies by type)
    config          JSONB NOT NULL DEFAULT '{}',
    -- round_robin: { "agents": ["uuid1", "uuid2"], "last_assigned_index": 0 }
    -- least_busy: { "agents": ["uuid1", "uuid2"], "max_conversations": 10 }
    -- tag_based: { "tag": "ventas", "agents": ["uuid1"] }
    -- manual: {}

    created_at      TIMESTAMP NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMP NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_assignment_rules_tenant ON assignment_rules(tenant_id, priority);
```

### quick_replies

Pre-configured message templates per tenant.

```sql
CREATE TABLE quick_replies (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id       UUID NOT NULL REFERENCES tenants(id),

    shortcut        VARCHAR(50) NOT NULL,  -- /precio, /horario, /ubicacion
    title           VARCHAR(255) NOT NULL,
    body            TEXT NOT NULL,

    -- Variables: {{contact_name}}, {{agent_name}}, {{business_name}}
    has_variables   BOOLEAN NOT NULL DEFAULT false,

    category        VARCHAR(100),  -- ventas, soporte, general
    usage_count     INTEGER NOT NULL DEFAULT 0,

    created_at      TIMESTAMP NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMP NOT NULL DEFAULT NOW(),

    UNIQUE(tenant_id, shortcut)
);

CREATE INDEX idx_quick_replies_tenant ON quick_replies(tenant_id);
```

### automations

Simple automation rules (auto-reply, welcome message, etc.).

```sql
CREATE TABLE automations (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id       UUID NOT NULL REFERENCES tenants(id),

    name            VARCHAR(255) NOT NULL,
    trigger_type    VARCHAR(50) NOT NULL,
        -- new_conversation, keyword, outside_hours, no_response_timeout

    trigger_config  JSONB NOT NULL DEFAULT '{}',
    -- new_conversation: {}
    -- keyword: { "keywords": ["precio", "cotizar"], "match": "contains" }
    -- outside_hours: {}
    -- no_response_timeout: { "minutes": 30 }

    action_type     VARCHAR(50) NOT NULL,
        -- send_message, assign_agent, add_tag, move_stage

    action_config   JSONB NOT NULL DEFAULT '{}',
    -- send_message: { "body": "Hola {{contact_name}}, gracias por escribirnos..." }
    -- assign_agent: { "user_id": "uuid" }
    -- add_tag: { "tag": "interesado" }
    -- move_stage: { "stage": "qualified" }

    is_active       BOOLEAN NOT NULL DEFAULT true,
    priority        INTEGER NOT NULL DEFAULT 0,
    execution_count INTEGER NOT NULL DEFAULT 0,

    created_at      TIMESTAMP NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMP NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_automations_tenant ON automations(tenant_id, trigger_type);
```

### webhook_logs

Audit log for all incoming webhooks (for debugging and replay).

```sql
CREATE TABLE webhook_logs (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id       UUID,  -- May be null if tenant can't be resolved

    source          VARCHAR(50) NOT NULL DEFAULT 'whatsapp',
    event_type      VARCHAR(100) NOT NULL,
    payload         JSONB NOT NULL,

    processed       BOOLEAN NOT NULL DEFAULT false,
    error_message   TEXT,

    created_at      TIMESTAMP NOT NULL DEFAULT NOW()
);

-- Partitioned by month for efficient cleanup
CREATE INDEX idx_webhook_logs_tenant ON webhook_logs(tenant_id, created_at DESC);
CREATE INDEX idx_webhook_logs_event ON webhook_logs(event_type, created_at DESC);
```

---

## Key Queries

### Inbox: List conversations for tenant (ordered by last message)

```sql
SELECT c.*, ct.name as contact_name, ct.phone, ct.profile_pic_url,
       m.body as last_message_body, m.direction as last_message_direction
FROM conversations c
JOIN contacts ct ON ct.id = c.contact_id
LEFT JOIN LATERAL (
    SELECT body, direction FROM messages
    WHERE conversation_id = c.id
    ORDER BY created_at DESC LIMIT 1
) m ON true
WHERE c.tenant_id = :tenant_id
  AND c.status = 'open'
ORDER BY c.last_message_at DESC
LIMIT 50;
```

### Dt1 Calculation

```sql
SELECT
    AVG(EXTRACT(EPOCH FROM (first_response_at - first_message_at))) as avg_dt1_seconds,
    PERCENTILE_CONT(0.5) WITHIN GROUP (
        ORDER BY EXTRACT(EPOCH FROM (first_response_at - first_message_at))
    ) as median_dt1_seconds
FROM conversations
WHERE tenant_id = :tenant_id
  AND first_message_at IS NOT NULL
  AND first_response_at IS NOT NULL
  AND created_at >= :start_date;
```

### Pipeline conversion rates

```sql
SELECT
    stage,
    COUNT(*) as total,
    COUNT(*) FILTER (WHERE stage != 'closed_lost') as active,
    SUM(value) as total_value
FROM pipeline_deals
WHERE tenant_id = :tenant_id
GROUP BY stage;
```

---

## Migration Order

```
1. create_tenants_table
2. create_users_table
3. create_contacts_table
4. create_conversations_table
5. create_messages_table
6. create_pipeline_deals_table
7. create_assignment_rules_table
8. create_quick_replies_table
9. create_automations_table
10. create_webhook_logs_table
```

---

## Indexing Strategy

- All `tenant_id` columns: B-tree index (every query filters by tenant)
- `contacts.tags`: GIN index (JSONB array containment queries)
- `conversations.last_message_at`: B-tree DESC (inbox ordering)
- `messages.conversation_id + created_at`: Composite (message thread loading)
- `messages.wa_message_id`: Unique-ish for deduplication
- `webhook_logs`: Partitioned by month, auto-drop after 90 days

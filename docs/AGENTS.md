# Agents & Service Responsibilities

This document defines the boundaries, responsibilities, and communication patterns of each service/agent in the AriCRM platform.

> Brand identity, tone of voice, and marketing context: see [`BRAND.md`](BRAND.md)

---

## Development Handoff Notes

### 2026-03-07 - Test baseline stabilization

- Fixed test migration crash in SQLite by guarding PostgreSQL-only GIN index creation in `database/migrations/2024_01_01_000002_create_contacts_table.php`.
- Updated `tests/Feature/ExampleTest.php` to assert redirect from `/` to `/dashboard`, matching current route behavior.
- Updated `tests/Feature/Auth/RegistrationTest.php` to include `company` (required by custom multi-tenant registration flow).
- Updated `tests/Feature/ProfileTest.php` to create users with tenant context and to assert soft delete behavior for account deletion.
- Reason: `php artisan test` was failing at migration time (`CREATE INDEX ... USING GIN`) before feature tests could run.
- Result after fixes: `php artisan test` passes (`25 passed`, `0 failed`).
- Follow-up for next agent: keep PostgreSQL optimizations, but always guard driver-specific SQL so CI/testing can run on SQLite.

### 2026-03-07 - Webhook authentication hardening

- Secured `POST /api/v1/webhooks/evolution` in `app/Http/Controllers/WebhookController.php`.
- Added required API key validation against `EVOLUTION_API_KEY` (`services.evolution.key`).
- Accepted inbound key formats: `apikey`, `x-api-key`, `x-evolution-apikey`, or `Authorization: Bearer <key>`.
- Unauthorized requests now return `401`; misconfigured server without key returns `503`.
- Webhook logs now persist `tenant_id` when `instance` maps to a tenant.
- Added regression tests in `tests/Feature/WebhookSecurityTest.php` for reject/accept behavior.
- Result after fixes: `php artisan test` passes (`27 passed`, `0 failed`).

### 2026-03-07 - API/docs alignment + usable Contacts/Pipeline

- Extended `GET /api/v1/conversations` filtering in `app/Http/Controllers/Api/V1/ConversationController.php`:
  - Supports `status`, `assigned`, `search`, `limit` with cursor pagination.
- Added `GET /api/v1/contacts` endpoint (`ContactController@index`) with `search`, `sort`, `direction`, `per_page`.
- Added `GET /api/v1/pipeline/deals` endpoint (`PipelineDealController@index`) with `stage`, `search`, `per_page`.
- Added `PipelineDealResource` and expanded `ContactResource` (`updated_at`, `assignee`).
- Replaced web placeholders:
  - `/contacts` now renders `resources/js/Pages/Contacts/Index.tsx`.
  - `/pipeline` now renders `resources/js/Pages/Pipeline/Index.tsx`.
- Updated `docs/API.md` to match implemented contracts and endpoint names.
- Validation: `php artisan test` and `npm run build` both passing after changes.

---

## 1. AriCRM Core (Laravel Application)

**Role**: Central orchestrator. All business logic lives here.

### Responsibilities

- **Authentication & Authorization**: User login, role-based access (owner, admin, agent), Sanctum tokens
- **Multi-tenancy**: Tenant resolution, data isolation, plan enforcement
- **Contact Management**: CRUD, deduplication by wa_id, merge, tagging
- **Conversation Management**: Open/close, assign, track status, calculate metrics
- **Message Handling**: Persist messages, trigger outbound sends via queue, track delivery status
- **Pipeline & Deals**: Stage management, value tracking, stage timestamps for bowtie metrics
- **Assignment Engine**: Apply tenant-configured rules (round-robin, least-busy, manual, tag-based)
- **Metrics Engine**: Calculate Dt1, conversion rates, response times, agent performance
- **Webhook Receiver**: Ingest events from WhatsApp Service, validate, dispatch to handlers
- **Job Dispatcher**: Queue async work (send messages, sync media, notifications)
- **Event Broadcasting**: Push real-time updates to frontend via Reverb

### Key Laravel Services (App\Services\)

```
TenantService          — Tenant resolution, plan limits
ContactService         — Contact CRUD, dedup, merge
ConversationService    — Conversation lifecycle, assignment
MessageService         — Send/receive orchestration
PipelineService        — Deal stage management
AssignmentService      — Rule-based agent assignment
MetricsService         — Dt1, performance calculations
WhatsAppClientService  — HTTP client to Evolution API
WebhookHandlerService  — Process incoming WA webhooks
MediaService           — Upload/download media to S3
```

### Key Laravel Jobs (App\Jobs\)

```
SendWhatsAppMessage    — Send outbound message via WA Service
ProcessIncomingMessage — Handle webhook payload, create records
SyncMediaFile          — Download media from WA, upload to S3
CalculateMetrics       — Periodic metric recalculation
CheckInstanceHealth    — Verify WA connection status per tenant
```

### Key Events (App\Events\)

```
MessageReceived        — Broadcast to inbox (real-time)
MessageSent            — Update message status in UI
MessageStatusUpdated   — Delivery/read receipts
ConversationAssigned   — Notify assigned agent
ConversationClosed     — Update UI, trigger metrics
InstanceStatusChanged  — WA connection status change
```

---

## 2. WhatsApp Service (Evolution API — Docker)

**Role**: WhatsApp protocol bridge. Manages WA connections and message transport.

### Responsibilities

- **Instance Management**: Create/delete WA instances (one per tenant)
- **QR Code Generation**: Generate and serve QR for number linking
- **Session Persistence**: Maintain WA sessions across restarts (Redis-backed)
- **Auto-reconnection**: Detect disconnects and reconnect automatically
- **Message Transport**: Send and receive text, media, documents, audio, video
- **Webhook Dispatch**: Forward all WA events to Laravel via HTTP POST
- **Media Handling**: Receive media files, store temporarily, provide download URL
- **Status Tracking**: Report instance status (connected, disconnected, qr_pending, banned)

### API Endpoints Consumed by Laravel

```
POST   /instance/create          — Create new WA instance
GET    /instance/connect/{name}  — Get QR code
DELETE /instance/delete/{name}   — Remove instance
GET    /instance/connectionState/{name} — Check status

POST   /message/sendText         — Send text message
POST   /message/sendMedia        — Send media message
POST   /message/sendDocument     — Send document

GET    /chat/findMessages/{instance} — Fetch message history
```

### Webhooks Sent to Laravel

```
POST /api/v1/webhooks/whatsapp
Events:
  - messages.upsert        — New message received
  - messages.update        — Message status update (delivered, read)
  - connection.update      — Connection state changed
  - qrcode.updated         — New QR code generated
  - instance.status        — Instance health change
```

---

## 3. Frontend (React + Inertia.js)

**Role**: User interface. Renders pages server-side via Inertia, enhances with React.

### Responsibilities

- **Inbox View**: Real-time conversational inbox with message list and composer
- **Contact Management**: Contact list, detail view, edit, merge UI
- **Pipeline Board**: Kanban-style deal pipeline (drag & drop stages)
- **Dashboard**: Metrics display (Dt1, response times, conversion)
- **Settings**: Tenant config, WA connection (QR scan), team management, assignment rules
- **Notifications**: Toast notifications for new messages, assignments

### Key Pages (Inertia)

```
Dashboard              — Metrics overview
Inbox                  — Conversational inbox (main workspace)
Contacts/Index         — Contact list with filters
Contacts/Show          — Contact detail + conversation history
Pipeline/Board         — Kanban deal board
Settings/General       — Tenant settings
Settings/WhatsApp      — QR connection, instance status
Settings/Team          — Users, roles, assignment rules
Settings/Automations   — Auto-replies, chatbot rules
```

### Real-time Channels (Echo + Reverb)

```
private-tenant.{tenantId}          — Tenant-wide events
private-conversation.{id}          — Conversation-specific updates
private-user.{userId}              — Personal notifications
presence-tenant.{tenantId}.agents  — Online agent tracking
```

---

## 4. Queue Worker (Laravel Horizon)

**Role**: Async job processing. Handles all background work.

### Queue Configuration

```
high     — SendWhatsAppMessage, ProcessIncomingMessage (latency-sensitive)
default  — SyncMediaFile, notifications
low      — CalculateMetrics, CheckInstanceHealth, cleanup jobs
```

### Monitoring

- Horizon dashboard at `/horizon` (admin-only)
- Track failed jobs, retry policy, queue depths
- Alert on queue backup > 100 jobs on `high` queue

---

## 5. Redis

**Role**: Multi-purpose in-memory store.

### Usage Partitions

```
DB 0  — Laravel cache (tenant configs, computed metrics)
DB 1  — Laravel session store
DB 2  — Queue (Horizon job storage)
DB 3  — Evolution API session data
DB 4  — Rate limiting counters
```

---

## 6. PostgreSQL

**Role**: Primary data store. Single database, multi-tenant via tenant_id.

### Key Concerns

- All tenant-scoped tables have `tenant_id` column with index
- Row-level isolation enforced via Laravel global scopes
- Connection pooling via PgBouncer in production
- Daily automated backups
- Read replicas for metric queries at scale

---

## 7. S3 / MinIO (Media Storage)

**Role**: Binary file storage for WhatsApp media.

### Bucket Structure

```
velo-media/
  {tenant_id}/
    images/
    audio/
    video/
    documents/
```

### Flow

1. WA Service receives media → stores temporarily
2. Laravel job downloads from WA Service → uploads to S3
3. S3 pre-signed URLs used for frontend display
4. Cleanup job removes files older than retention period (per tenant plan)

---

## Communication Matrix

| From → To | Protocol | Purpose |
|-----------|----------|---------|
| Frontend → Laravel | HTTP (Inertia) | Page loads, form submissions |
| Frontend → Laravel | HTTP (API) | Inbox actions, message send |
| Laravel → Frontend | WebSocket (Reverb) | Real-time updates |
| Laravel → WA Service | HTTP REST | Send messages, manage instances |
| WA Service → Laravel | HTTP Webhook | Incoming messages, status updates |
| Laravel → Redis | TCP | Cache, queue, sessions |
| Laravel → PostgreSQL | TCP | Data persistence |
| Laravel → S3 | HTTP | Media storage/retrieval |
| Horizon → Redis | TCP | Job processing |

---

## Error Handling & Resilience

### WA Service Unavailable
- Outbound messages queued in Redis, retried with exponential backoff (3 attempts)
- Instance health check job runs every 5 minutes
- Frontend shows "Disconnected" badge on tenant's WA status

### Webhook Delivery Failure
- Evolution API retries webhooks 3 times with backoff
- Laravel logs all raw webhook payloads for replay capability
- Dead letter queue for failed webhook processing

### Rate Limiting
- Outbound messages throttled per instance: max 30/minute (configurable)
- Burst protection: if queue depth > 50 for single instance, pause and alert
- Ban detection: if WA returns ban signal, mark instance and alert tenant

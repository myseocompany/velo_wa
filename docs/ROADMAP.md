# Development Roadmap

## Phase 0: Foundation (Week 1-2) ✅ COMPLETE
> Goal: Project scaffold, infrastructure, and basic auth

### Tasks
- [x] Initialize Laravel 11 project with Inertia.js + React + TypeScript
- [x] Configure Docker Compose: PostgreSQL, Redis, Evolution API, MinIO
- [x] Set up Tailwind CSS + Shadcn/UI component library
- [x] Implement multi-tenancy: tenant model, middleware, global scopes
- [x] Auth: registration, login, Sanctum SPA auth
- [x] User model with roles (owner, admin, agent)
- [x] Create all database migrations (tenants, users, contacts, conversations, messages, deals)
- [x] Set up Laravel Horizon for queue processing
- [x] Set up Laravel Reverb for WebSocket broadcasting
- [x] Configure S3/MinIO for media storage
- [x] Write `.env.example` with all required variables
- [x] Basic layout: sidebar navigation, responsive shell

### Deliverable
Running local dev environment with auth, multi-tenancy, and all services healthy.

---

## Phase 1: WhatsApp Connection (Week 3-4) ✅ COMPLETE
> Goal: Connect a WhatsApp number and receive/send messages

### Tasks
- [x] `WhatsAppClientService`: HTTP client to Evolution API
- [x] Settings page: WhatsApp connection with QR code display
- [x] QR generation flow: create instance → display QR → poll/webhook for connection
- [x] Webhook endpoint: receive and log all Evolution API events
- [x] `WebhookHandlerService`: route events by type
- [x] Handle `CONNECTION_UPDATE`: update tenant wa_status in real-time
- [x] Handle `MESSAGES_UPSERT`: create contact + conversation + message
- [x] Handle `MESSAGES_UPDATE`: update message delivery status
- [x] `SendWhatsAppMessage` job: send text via Evolution API
- [x] Basic inbox page: list conversations, view messages, send text replies
- [x] Real-time: broadcast new messages via Reverb, update inbox with Echo
- [x] Instance health check job (every 5 minutes)

### Deliverable
Can connect WhatsApp via QR, receive messages from leads, and reply from the inbox in real-time.

---

## Phase 2: Inbox & Conversations (Week 5-6) ✅ COMPLETE
> Goal: Full conversational inbox experience

### Tasks
- [x] Inbox UI: conversation list (sidebar) + message thread (main area)
- [x] Conversation search and filtering (open/closed/assigned)
- [x] Message composer: text input, emoji picker, quick reply shortcut trigger
- [x] Media support: send/receive images, documents, audio, video
- [x] Media handling: S3 upload/download, pre-signed URLs, thumbnails
- [x] Quick replies: CRUD, `/shortcut` trigger in composer, variable interpolation
- [x] Conversation actions: assign, close, reopen
- [x] Unread count badges, sound notifications
- [x] Message status indicators (pending → sent → delivered → read)
- [x] Contact info panel (sidebar in conversation view)
- [x] Online presence: show which agents are online (presence channel)
- [x] Infinite scroll for message history

### Deliverable
Production-quality inbox that feels like WhatsApp Web, with media and quick replies.

---

## Phase 3: Contacts & Assignment (Week 7-8) ✅ COMPLETE (with known gaps)
> Goal: Contact management and automatic conversation assignment

### Tasks
- [x] Contact list page: search, filter by tags, sort, paginate
- [x] Contact detail page: info, conversation history, deals, edit
- [x] Contact merge: detect duplicates by phone, merge records
- [x] Tagging system: add/remove tags, filter by tags
- [ ] Custom fields: schema and model support exist, functional editing not exposed in UI
- [x] Assignment rules: CRUD, priority ordering
- [x] Assignment engine: round-robin implementation
- [x] Assignment engine: least-busy implementation
- [x] Assignment engine: tag-based routing
- [x] Assignment engine: manual (notify all, first-claim)
- [x] Auto-assign on new conversation (apply rules)
- [x] Reassignment: manual override by admin
- [ ] Agent workload view: conversations per agent (not yet built)

### Deliverable
Contacts are organized, tagged, and conversations auto-assign to the right agent.

---

## Phase 4: Pipeline & Deals (Week 9-10) ✅ COMPLETE
> Goal: Visual sales pipeline with bowtie metrics

### Tasks
- [x] Pipeline board: Kanban UI with drag-and-drop stages
- [x] Deal CRUD: create from contact/conversation, edit, delete
- [x] Stage transitions: auto-timestamp, validate allowed transitions
- [x] Deal value tracking per stage
- [x] Link deals to conversations (context in inbox)
- [x] Pipeline filters: by stage, agent, date range, value
- [x] Stage duration metrics: time in each stage
- [x] Conversion funnel: visual bowtie chart
- [x] Won/lost tracking with reasons
- [x] Pipeline summary cards: total value, weighted forecast

### Deliverable
Visual pipeline board where agents manage deals from lead to close.

---

## Phase 5: Metrics & Dashboard (Week 11-12) ✅ COMPLETE (with known gaps)
> Goal: Key performance metrics and dashboard

### Tasks
- [x] Dashboard page layout with metric cards and charts
- [x] Dt1 calculation: average, median, P95 response time
- [ ] Dt1 per agent breakdown (not yet built)
- [x] Conversations over time chart (daily/weekly/monthly)
- [x] Messages volume chart (inbound vs outbound)
- [x] Pipeline conversion rates and velocity
- [ ] Agent performance table: Dt1, conversations handled, messages sent (not yet built)
- [x] Date range picker for all metrics
- [x] Business hours filter (exclude off-hours from Dt1)
- [ ] Export metrics to CSV (not yet built)
- [x] Periodic metric recalculation job (hourly, now wired to dashboard cache)

### Deliverable
Dashboard showing team performance, response times, and pipeline health.

---

## Phase 6: Automations (Week 13-14) ✅ COMPLETE
> Goal: Simple automation rules for common scenarios

### Tasks
- [x] Automation engine: rule matching and execution
- [x] Trigger: new conversation → send welcome message
- [x] Trigger: keyword match → auto-reply or route
- [x] Trigger: outside business hours → send away message
- [x] Trigger: no response timeout → send follow-up (with idempotency)
- [x] Action: send message (with variable interpolation)
- [x] Action: assign to specific agent
- [x] Action: add tag to contact
- [x] Action: move deal to stage
- [x] Automation CRUD UI in settings
- [x] Automation execution logging (automation_logs table + UI drawer)
- [x] Automation enable/disable toggle

### Deliverable
Tenants can set up auto-replies, welcome messages, and keyword-based routing without code.

---

## Phase 7: Team & Settings (Week 15-16) ✅ COMPLETE
> Goal: Team management, tenant settings, polish

### Tasks
- [x] Team management: invite, edit roles, deactivate/reactivate
- [x] Role-based access control enforcement across all pages (nav + API)
- [x] Tenant settings: timezone, business hours, auto-close timer
- [x] Notification preferences per user (stored per-user in DB)
- [x] Tenant plan limits enforcement (max agents on invite/reactivate)
- [x] Profile settings: name, password, avatar (S3 upload)
- [x] Activity log: who did what (spatie/laravel-activitylog, audit trail for contacts/conversations/deals/team)
- [x] Error handling: user-friendly error pages (403/404/500/503 via Inertia)
- [x] Loading states, skeleton screens in all new pages
- [x] Empty states in team and activity log pages
- [ ] Mobile responsive polish (deferred to Phase 8)
- [ ] End-to-end testing of critical flows (deferred to Phase 8)

### Deliverable
Complete application ready for beta users.

---

## Phase 7.5: Platform Admin Panel (Week 16-17) ✅ COMPLETE
> Goal: Super admin panel for platform operators — completely separate from tenant context

### Context
The platform admin is **not** a tenant user. It lives outside the tenancy system with its own guard, routes, and audit trail. Needed before onboarding real customers.

### Tasks
- [x] `platform_admins` table (separate from `users`, no `tenant_id`)
- [x] `platform` guard with its own session/auth logic (Laravel multi-guard)
- [x] Routes under `/superadmin` protected by `EnsurePlatformAdmin` + `EnsurePlatformAdminTwoFactor` middleware
- [x] Login page for platform admins (isolated from tenant login)
- [x] Tenant list: name, slug, WA status, plan, created_at, agents/contacts counts
- [x] Tenant detail: overview of contacts, conversations, deals + member list
- [x] Impersonate tenant: enter as owner — action logged, visible amber banner while impersonating
- [x] WhatsApp instance management: force disconnect, status badge
- [x] Tenant plan assignment: set max_agents, max_contacts limits
- [x] Audit log: all platform admin actions with timestamp, IP, affected tenant, metadata
- [x] TOTP 2FA enforcement (pure PHP RFC 6238, no packages) with setup/enable/disable flow

### Security Rules
- Platform admin bypass of tenant global scopes is **explicit and logged** — never implicit
- Impersonation creates an audit record before granting access
- Platform admin session is separate from any tenant session

### Deliverable
Operators can monitor all tenants, diagnose issues, and impersonate for support — with full audit trail.

---

## Phase 8: Production & Launch (Week 17-18) ✅ COMPLETE
> Goal: Deploy, monitor, iterate

### Tasks
- [x] Production deployment: `docker-compose.prod.yml` — nginx, app (PHP-FPM), horizon, reverb, scheduler, certbot
- [x] SSL: Let's Encrypt via certbot + Nginx HTTPS config (`docker/nginx/prod.conf`)
- [x] Backup: `scripts/backup.sh` — pg_dump | gzip → S3 upload with 30-day retention
- [x] Deploy: `scripts/deploy.sh` — zero-downtime rolling deploy with cache warmup
- [x] Logging: daily rotation + Slack error channel (`LOG_STACK=daily,slack`)
- [x] Rate limiting: per-tenant throttle — 120/min API, 30/min messages, 5/min WA control, 500/min webhooks
- [x] Onboarding: 3-step wizard `/onboarding`, `EnsureOnboardingComplete` middleware, DB column `onboarding_completed_at`
- [x] Landing page: full marketing page — hero, features, testimonials, pricing grid
- [x] Billing: `laravel/cashier` Stripe — checkout sessions, billing portal, cancel/resume, webhook plan-limit sync
- [x] Dockerfile.prod: PHP-FPM Alpine image with asset build baked in
- [x] `.env.production.example`: all production variables documented

### Deliverable
AriCRM ready for production — infrastructure, billing, and onboarding fully in place.

---

## Future Enhancements (Backlog)

- [ ] WhatsApp Business API migration option (for tenants that qualify)
- [ ] Baileys direct microservice (replace Evolution API at scale)
- [ ] AI-powered suggested replies
- [ ] Chatbot builder (visual flow)
- [ ] Multi-channel: Instagram DM, Facebook Messenger
- [ ] Bulk messaging / campaigns (with opt-in compliance)
- [ ] API for external integrations
- [ ] Zapier / Make.com integration
- [ ] White-label option
- [ ] Mobile app (React Native)

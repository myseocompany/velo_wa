# Development Roadmap

## Phase 0: Foundation (Week 1-2)
> Goal: Project scaffold, infrastructure, and basic auth

### Tasks
- [ ] Initialize Laravel 11 project with Inertia.js + React + TypeScript
- [ ] Configure Docker Compose: PostgreSQL, Redis, Evolution API, MinIO
- [ ] Set up Tailwind CSS + Shadcn/UI component library
- [ ] Implement multi-tenancy: tenant model, middleware, global scopes
- [ ] Auth: registration, login, Sanctum SPA auth
- [ ] User model with roles (owner, admin, agent)
- [ ] Create all database migrations (tenants, users, contacts, conversations, messages, deals)
- [ ] Set up Laravel Horizon for queue processing
- [ ] Set up Laravel Reverb for WebSocket broadcasting
- [ ] Configure S3/MinIO for media storage
- [ ] Write `.env.example` with all required variables
- [ ] Basic layout: sidebar navigation, responsive shell

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

## Phase 2: Inbox & Conversations (Week 5-6)
> Goal: Full conversational inbox experience

### Tasks
- [ ] Inbox UI: conversation list (sidebar) + message thread (main area)
- [ ] Conversation search and filtering (open/closed/assigned)
- [ ] Message composer: text input, emoji picker, quick reply shortcut trigger
- [ ] Media support: send/receive images, documents, audio, video
- [ ] Media handling: S3 upload/download, pre-signed URLs, thumbnails
- [ ] Quick replies: CRUD, `/shortcut` trigger in composer, variable interpolation
- [ ] Conversation actions: assign, close, reopen
- [ ] Unread count badges, sound notifications
- [ ] Message status indicators (pending → sent → delivered → read)
- [ ] Contact info panel (sidebar in conversation view)
- [ ] Online presence: show which agents are online (presence channel)
- [ ] Infinite scroll for message history

### Deliverable
Production-quality inbox that feels like WhatsApp Web, with media and quick replies.

---

## Phase 3: Contacts & Assignment (Week 7-8)
> Goal: Contact management and automatic conversation assignment

### Tasks
- [ ] Contact list page: search, filter by tags, sort, paginate
- [ ] Contact detail page: info, conversation history, deals, edit
- [ ] Contact merge: detect duplicates by phone, merge records
- [ ] Tagging system: add/remove tags, filter by tags
- [ ] Custom fields: tenant-configurable extra fields on contacts
- [ ] Assignment rules: CRUD, priority ordering
- [ ] Assignment engine: round-robin implementation
- [ ] Assignment engine: least-busy implementation
- [ ] Assignment engine: tag-based routing
- [ ] Assignment engine: manual (notify all, first-claim)
- [ ] Auto-assign on new conversation (apply rules)
- [ ] Reassignment: manual override by admin
- [ ] Agent workload view: conversations per agent

### Deliverable
Contacts are organized, tagged, and conversations auto-assign to the right agent.

---

## Phase 4: Pipeline & Deals (Week 9-10)
> Goal: Visual sales pipeline with bowtie metrics

### Tasks
- [ ] Pipeline board: Kanban UI with drag-and-drop stages
- [ ] Deal CRUD: create from contact/conversation, edit, delete
- [ ] Stage transitions: auto-timestamp, validate allowed transitions
- [ ] Deal value tracking per stage
- [ ] Link deals to conversations (context in inbox)
- [ ] Pipeline filters: by stage, agent, date range, value
- [ ] Stage duration metrics: time in each stage
- [ ] Conversion funnel: visual bowtie chart
- [ ] Won/lost tracking with reasons
- [ ] Pipeline summary cards: total value, weighted forecast

### Deliverable
Visual pipeline board where agents manage deals from lead to close.

---

## Phase 5: Metrics & Dashboard (Week 11-12)
> Goal: Key performance metrics and dashboard

### Tasks
- [ ] Dashboard page layout with metric cards and charts
- [ ] Dt1 calculation: average, median, P95 response time
- [ ] Dt1 per agent breakdown
- [ ] Conversations over time chart (daily/weekly/monthly)
- [ ] Messages volume chart (inbound vs outbound)
- [ ] Pipeline conversion rates and velocity
- [ ] Agent performance table: Dt1, conversations handled, messages sent
- [ ] Date range picker for all metrics
- [ ] Business hours filter (exclude off-hours from Dt1)
- [ ] Export metrics to CSV
- [ ] Periodic metric recalculation job (hourly)

### Deliverable
Dashboard showing team performance, response times, and pipeline health.

---

## Phase 6: Automations (Week 13-14)
> Goal: Simple automation rules for common scenarios

### Tasks
- [ ] Automation engine: rule matching and execution
- [ ] Trigger: new conversation → send welcome message
- [ ] Trigger: keyword match → auto-reply or route
- [ ] Trigger: outside business hours → send away message
- [ ] Trigger: no response timeout → send follow-up
- [ ] Action: send message (with variable interpolation)
- [ ] Action: assign to specific agent
- [ ] Action: add tag to contact
- [ ] Action: move deal to stage
- [ ] Automation CRUD UI in settings
- [ ] Automation execution logging
- [ ] Automation enable/disable toggle

### Deliverable
Tenants can set up auto-replies, welcome messages, and keyword-based routing without code.

---

## Phase 7: Team & Settings (Week 15-16)
> Goal: Team management, tenant settings, polish

### Tasks
- [ ] Team management: invite, edit roles, deactivate
- [ ] Role-based access control enforcement across all pages
- [ ] Tenant settings: timezone, business hours, auto-close timer
- [ ] Notification preferences per user
- [ ] Tenant plan limits enforcement (max agents, contacts, storage)
- [ ] Profile settings: name, password, avatar
- [ ] Activity log: who did what (audit trail)
- [ ] Error handling and user-friendly error pages
- [ ] Loading states, empty states, skeleton screens
- [ ] Mobile responsive polish
- [ ] End-to-end testing of critical flows

### Deliverable
Complete application ready for beta users.

---

## Phase 8: Production & Launch (Week 17-18)
> Goal: Deploy, monitor, iterate

### Tasks
- [ ] Production deployment: VPS + Docker Compose
- [ ] SSL certificates (Let's Encrypt)
- [ ] Backup strategy: automated daily PostgreSQL backups
- [ ] Monitoring: application health, queue depth, error rates
- [ ] Logging: centralized log aggregation
- [ ] Rate limiting: enforce per-tenant API limits
- [ ] Onboarding flow: first-time user experience
- [ ] Landing page with signup
- [ ] Billing integration (Stripe or local payment gateway)
- [ ] Documentation for end users
- [ ] Beta user onboarding (5-10 tenants)
- [ ] Feedback collection and iteration

### Deliverable
Velo live in production with paying customers.

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

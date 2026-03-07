# Velo - WhatsApp CRM SaaS

## Project Overview

Velo is a multi-tenant SaaS platform that connects businesses to WhatsApp for lead management, conversational inbox, and sales pipeline tracking. Each tenant connects their own WhatsApp number via QR scan and manages contacts, conversations, and deals through a unified interface.

## Tech Stack

- **Backend**: Laravel 11+ (PHP 8.3)
- **Frontend**: React (Inertia.js) + Tailwind CSS
- **WhatsApp Service**: Evolution API (Docker, self-hosted) — future migration path to Baileys direct
- **Database**: PostgreSQL 16
- **Queue**: Redis + Laravel Horizon
- **Real-time**: Laravel Reverb (WebSockets)
- **Media Storage**: S3-compatible (MinIO for dev, AWS S3 for prod)
- **Cache/Sessions**: Redis
- **Containerization**: Docker Compose (all services)

## Development Conventions

### Backend (Laravel)

- Use strict types: `declare(strict_types=1);` in all PHP files
- Follow PSR-12 coding standard
- Use Laravel Actions pattern for business logic (not fat controllers/models)
- All database queries must be tenant-scoped — use global scopes on models
- Use Form Requests for validation, never validate in controllers
- Use Laravel Data (Spatie) for DTOs between layers
- Use Enums (PHP 8.1 backed enums) for statuses, types, stages
- Write feature tests for every endpoint, unit tests for business logic
- Name migrations descriptively: `create_contacts_table`, `add_wa_status_to_tenants`

### Frontend (React)

- Use TypeScript strict mode
- Components in PascalCase, hooks in camelCase with `use` prefix
- Use Inertia.js for page routing (no SPA router)
- Shared types in `resources/js/types/`
- Use Shadcn/UI as component library base
- Real-time updates via Echo + Reverb

### Multi-tenancy

- Tenancy model: single database, tenant_id column on all tenant-scoped tables
- Auth: Laravel Sanctum for SPA, API tokens for external integrations
- Middleware `EnsureTenantContext` on all tenant routes
- Never expose data across tenants — this is a critical security requirement

### WhatsApp Service

- Evolution API runs as separate Docker container
- Laravel communicates via HTTP REST calls to Evolution API
- Evolution API sends webhooks to Laravel for incoming events
- One Evolution API instance manages multiple WhatsApp instances (one per tenant)
- All media files are stored in S3, never in local filesystem

### API Design

- Internal API: RESTful, versioned (`/api/v1/`)
- Use Laravel API Resources for response formatting
- Consistent error format: `{ "message": "", "errors": {} }`
- Pagination: cursor-based for conversations/messages, offset for contacts/deals

### Database

- Always use UUIDs as primary keys (for multi-tenant safety and API exposure)
- Timestamps on every table: `created_at`, `updated_at`
- Soft deletes on: contacts, conversations, deals
- Index all `tenant_id` columns and foreign keys
- Use database transactions for multi-step operations

### Git Workflow

- Main branch: `main`
- Feature branches: `feature/short-description`
- Fix branches: `fix/short-description`
- Conventional commits: `feat:`, `fix:`, `refactor:`, `docs:`, `test:`, `chore:`
- PR-based workflow, squash merge to main

### Environment

- Local dev: Docker Compose (all services)
- `.env.example` always up to date
- Secrets never committed — use `.env` only
- Feature flags via config for gradual rollouts

## Key Files Reference

- `docs/ARCHITECTURE.md` — System architecture and data flows
- `docs/DATABASE.md` — Complete data model
- `docs/API.md` — API contracts and endpoints
- `docs/WHATSAPP_SERVICE.md` — WhatsApp integration specs
- `docs/AGENTS.md` — Agent/service responsibilities
- `docs/ROADMAP.md` — Phased development plan
- `docs/STACK.md` — Technology stack details

## Critical Business Rules

1. **Tenant isolation**: No data leakage between tenants, ever
2. **Dt1 (Delta t1)**: Time from first contact message to first agent response — key metric
3. **Assignment rules**: Configurable per tenant (round-robin, least-busy, manual)
4. **Message ordering**: Messages must display in correct chronological order
5. **Session reconnection**: WhatsApp sessions must auto-reconnect on disconnect
6. **Rate limiting**: Respect WhatsApp's unofficial rate limits to avoid bans

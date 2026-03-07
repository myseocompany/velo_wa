# Technology Stack & Infrastructure

## Core Stack

| Layer | Technology | Version | Purpose |
|-------|-----------|---------|---------|
| **Backend** | Laravel | 11.x | Core application, API, business logic |
| **Language** | PHP | 8.3+ | Backend runtime |
| **Frontend** | React | 18.x | UI components |
| **Bridge** | Inertia.js | 2.x | Server-driven SPA (Laravel ↔ React) |
| **TypeScript** | TypeScript | 5.x | Frontend type safety |
| **CSS** | Tailwind CSS | 3.x | Utility-first styling |
| **Components** | Shadcn/UI | latest | Pre-built React components |
| **Build** | Vite | 5.x | Frontend bundling (via Laravel Vite) |
| **Database** | PostgreSQL | 16 | Primary data store |
| **Cache/Queue** | Redis | 7.x | Cache, sessions, queue broker |
| **Queue Worker** | Laravel Horizon | latest | Queue management dashboard |
| **WebSocket** | Laravel Reverb | 1.x | Real-time broadcasting |
| **WS Client** | Laravel Echo | latest | Frontend WebSocket client |
| **WhatsApp** | Evolution API | 2.2.x | WhatsApp protocol bridge |
| **Media** | S3 / MinIO | latest | File storage |
| **Container** | Docker + Compose | latest | Development & production |

---

## Laravel Packages

| Package | Purpose |
|---------|---------|
| `laravel/sanctum` | SPA authentication + API tokens |
| `laravel/horizon` | Queue dashboard and management |
| `laravel/reverb` | WebSocket server |
| `inertiajs/inertia-laravel` | Inertia.js server adapter |
| `spatie/laravel-data` | DTOs and data objects |
| `spatie/laravel-query-builder` | API query filtering, sorting, includes |
| `spatie/laravel-permission` | Role and permission management |
| `spatie/laravel-activitylog` | Audit trail / activity logging |
| `league/flysystem-aws-s3-v3` | S3 filesystem adapter |
| `pestphp/pest` | Testing framework |
| `larastan/larastan` | Static analysis (PHPStan for Laravel) |
| `laravel/pint` | Code style fixer (PSR-12) |

---

## Frontend Packages (npm)

| Package | Purpose |
|---------|---------|
| `@inertiajs/react` | Inertia.js React adapter |
| `laravel-echo` | WebSocket client for Reverb |
| `pusher-js` | WebSocket protocol (used by Echo) |
| `@radix-ui/*` | Headless UI primitives (via Shadcn) |
| `class-variance-authority` | Component variant management |
| `clsx` + `tailwind-merge` | Conditional class names |
| `lucide-react` | Icon library |
| `@hello-pangea/dnd` | Drag and drop (pipeline board) |
| `date-fns` | Date formatting and manipulation |
| `recharts` | Charts for dashboard metrics |
| `zustand` | Lightweight state management (inbox state) |
| `react-hot-toast` | Toast notifications |

---

## Development Environment

### Docker Compose Services

```yaml
services:
  # Laravel application
  app:
    build: ./docker/app
    ports: ["8000:8000"]
    volumes: [".:/var/www"]
    depends_on: [postgres, redis]

  # Vite dev server (HMR)
  vite:
    build: ./docker/node
    ports: ["5173:5173"]
    volumes: [".:/var/www"]

  # PostgreSQL
  postgres:
    image: postgres:16
    ports: ["5432:5432"]
    volumes: [pgdata:/var/lib/postgresql/data]
    environment:
      POSTGRES_DB: velo
      POSTGRES_USER: velo
      POSTGRES_PASSWORD: secret

  # Redis
  redis:
    image: redis:7-alpine
    ports: ["6379:6379"]

  # Evolution API
  evolution-api:
    image: atendai/evolution-api:v2.2.3
    ports: ["8080:8080"]
    depends_on: [postgres, redis]

  # MinIO (S3-compatible for local dev)
  minio:
    image: minio/minio
    ports: ["9000:9000", "9001:9001"]
    command: server /data --console-address ":9001"
    volumes: [minio_data:/data]

  # Laravel Horizon (queue worker)
  horizon:
    build: ./docker/app
    command: php artisan horizon
    depends_on: [app, redis]

  # Laravel Reverb (WebSocket server)
  reverb:
    build: ./docker/app
    command: php artisan reverb:start
    ports: ["8080:8080"]
    depends_on: [app, redis]

  # Mailpit (email testing)
  mailpit:
    image: axllent/mailpit
    ports: ["8025:8025", "1025:1025"]
```

### Required Environment Variables

```env
# Application
APP_NAME=Velo
APP_ENV=local
APP_URL=http://localhost:8000

# Database
DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=velo
DB_USERNAME=velo
DB_PASSWORD=secret

# Redis
REDIS_HOST=redis
REDIS_PORT=6379

# Evolution API
EVOLUTION_API_URL=http://evolution-api:8080
EVOLUTION_API_KEY=your-secret-api-key

# S3 / MinIO
AWS_ACCESS_KEY_ID=minioadmin
AWS_SECRET_ACCESS_KEY=minioadmin
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=velo-media
AWS_ENDPOINT=http://minio:9000
AWS_USE_PATH_STYLE_ENDPOINT=true

# Broadcasting (Reverb)
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=velo
REVERB_APP_KEY=velo-key
REVERB_APP_SECRET=velo-secret
REVERB_HOST=localhost
REVERB_PORT=8080

# Queue
QUEUE_CONNECTION=redis

# Mail (Mailpit for dev)
MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025
```

---

## Project Directory Structure

```
velo_wa/
├── app/
│   ├── Actions/              # Business logic actions
│   │   ├── Contacts/
│   │   ├── Conversations/
│   │   ├── Messages/
│   │   ├── Pipeline/
│   │   └── WhatsApp/
│   ├── Data/                 # Spatie Data DTOs
│   ├── Enums/                # PHP backed enums
│   │   ├── ConversationStatus.php
│   │   ├── DealStage.php
│   │   ├── MessageDirection.php
│   │   ├── MessageStatus.php
│   │   ├── UserRole.php
│   │   └── WaConnectionStatus.php
│   ├── Events/               # Broadcast events
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Api/V1/       # API controllers
│   │   │   └── Web/          # Inertia page controllers
│   │   ├── Middleware/
│   │   │   └── EnsureTenantContext.php
│   │   └── Requests/         # Form request validation
│   ├── Jobs/                 # Queue jobs
│   ├── Models/               # Eloquent models
│   │   ├── Concerns/
│   │   │   └── BelongsToTenant.php  # Global scope trait
│   │   ├── Tenant.php
│   │   ├── User.php
│   │   ├── Contact.php
│   │   ├── Conversation.php
│   │   ├── Message.php
│   │   ├── PipelineDeal.php
│   │   ├── AssignmentRule.php
│   │   ├── QuickReply.php
│   │   ├── Automation.php
│   │   └── WebhookLog.php
│   ├── Services/             # Service classes
│   │   ├── WhatsAppClientService.php
│   │   ├── WebhookHandlerService.php
│   │   ├── AssignmentService.php
│   │   ├── MetricsService.php
│   │   └── MediaService.php
│   └── Policies/             # Authorization policies
├── config/
├── database/
│   ├── migrations/
│   ├── factories/
│   └── seeders/
├── docker/
│   ├── app/
│   │   └── Dockerfile
│   └── node/
│       └── Dockerfile
├── resources/
│   ├── js/
│   │   ├── app.tsx
│   │   ├── Components/       # Shared React components
│   │   │   ├── ui/           # Shadcn components
│   │   │   ├── Inbox/
│   │   │   ├── Pipeline/
│   │   │   └── Layout/
│   │   ├── Hooks/            # Custom React hooks
│   │   ├── Layouts/
│   │   │   ├── AppLayout.tsx
│   │   │   └── AuthLayout.tsx
│   │   ├── Pages/
│   │   │   ├── Auth/
│   │   │   ├── Dashboard.tsx
│   │   │   ├── Inbox/
│   │   │   ├── Contacts/
│   │   │   ├── Pipeline/
│   │   │   └── Settings/
│   │   ├── Stores/           # Zustand stores
│   │   └── Types/            # TypeScript types
│   │       ├── index.d.ts
│   │       ├── models.ts
│   │       └── api.ts
│   └── views/
│       └── app.blade.php     # Inertia root template
├── routes/
│   ├── web.php               # Inertia page routes
│   ├── api.php               # API routes
│   └── channels.php          # Broadcast channel auth
├── tests/
│   ├── Feature/
│   └── Unit/
├── docs/                     # Project documentation
├── docker-compose.yml
├── CLAUDE.md
├── .env.example
└── package.json
```

---

## Production Infrastructure

### Phase 1: Single VPS (MVP)

```
VPS (4 CPU, 8GB RAM, 80GB SSD) — ~$40/mo
├── Docker Compose
│   ├── Nginx (reverse proxy + SSL)
│   ├── Laravel app (PHP-FPM)
│   ├── Laravel Horizon (queue)
│   ├── Laravel Reverb (WebSocket)
│   ├── Evolution API
│   ├── PostgreSQL
│   ├── Redis
│   └── MinIO → (migrate to AWS S3 when ready)
├── Certbot (Let's Encrypt SSL)
├── Automated backups (pg_dump → S3)
└── Basic monitoring (Laravel Telescope + Horizon dashboard)
```

### Recommended VPS Providers

- **Hetzner**: Best price/performance for EU/US
- **DigitalOcean**: Good DX, simple scaling
- **Vultr**: Competitive pricing
- **AWS Lightsail**: If already in AWS ecosystem

### Domain & DNS

```
velo.app (or similar)
├── app.velo.app    → Laravel application
├── ws.velo.app     → Reverb WebSocket
├── api.velo.app    → (optional, can use app.velo.app/api)
└── media.velo.app  → S3/CDN for media (later)
```

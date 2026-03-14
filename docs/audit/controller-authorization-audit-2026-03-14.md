# Controller Authorization Audit

Date: 2026-03-14
Scope:
- `app/Http/Controllers/Api/V1/`
- `app/Http/Controllers/`
- Focus controllers: `AutomationController`, `ContactController`, `QuickReplyController`, `DashboardController`

## Objective

Audit each public controller method, especially `index`, `show`, `store`, `update`, and `destroy`, to verify whether access control is enforced in the backend through:

1. `$this->authorize()` or `authorizeResource()`
2. a Form Request with `authorize()` restricted by role
3. route role middleware

Also flag endpoints that rely only on frontend restrictions or have no RBAC gate at all.

## Executive Summary

The application enforces authentication, tenant membership, and active-user status, but not role-based authorization for the audited business endpoints.

What exists:
- API routes are protected by `auth:sanctum` and `tenant`.
- Web routes are protected by `auth`, `verified`, and `tenant`.
- Tenant-scoped models use a global tenant scope.

What is missing:
- No `$this->authorize()` calls in the audited controllers
- No `authorizeResource()` usage
- No policy classes under `app/Policies`
- No route role middleware alias
- No Form Request in the audited business area that restricts by owner/admin/agent role

Result:
- Most endpoints are callable by any authenticated tenant user.
- A smaller subset is even worse from a design perspective: the UI suggests role restrictions, but the backend does not enforce them.

## Methodology

Checked:
- route middleware in `routes/api.php` and `routes/web.php`
- middleware aliases in `bootstrap/app.php`
- controller methods under both controller trees
- Form Requests used by those controllers
- presence of policies or controller authorization calls
- frontend role checks to distinguish:
  - frontend-only restriction
  - no restriction at all

## Baseline Security Controls Present

### Route-level auth and tenant middleware

- API routes are grouped under `auth:sanctum` and `tenant` in `routes/api.php`.
- Web routes are grouped under `auth`, `verified`, and `tenant` in `routes/web.php`.

These controls ensure:
- user must be authenticated
- user must belong to a tenant
- user must be active

They do not ensure:
- only owners can manage settings
- only admins can reassign or manage team resources
- agents are prevented from mutating administrative resources

### Tenant isolation

`App\Models\Concerns\BelongsToTenant` adds a global scope to tenant-bound models and auto-populates `tenant_id` on create.

This protects against cross-tenant access, but it is not RBAC.

### Tenant middleware

`App\Http\Middleware\EnsureTenantContext` denies users without a tenant and inactive users.

This is an account/tenant validity check, not a role check.

## Global Findings

### 1. No controller-level authorization

No `authorizeResource()` and no `$this->authorize()` were found under:
- `app/Http/Controllers/Api/V1/`
- `app/Http/Controllers/`

### 2. No policy layer in use

`app/Policies` contains no policy files.

### 3. No role middleware alias

`bootstrap/app.php` only defines:
- `tenant`
- `instrument.dashboard`

No `role`, `permission`, or equivalent middleware alias exists.

### 4. Form Requests do not implement RBAC

Observed patterns:
- explicit `authorize(): bool { return true; }`
- no `authorize()` method at all

In Laravel, when `authorize()` is absent in a Form Request, the request still passes authorization by default.

## Focus Controller Findings

## 1. AutomationController

File: `app/Http/Controllers/Api/V1/AutomationController.php`

Methods reviewed:
- `index`
- `store`
- `update`
- `destroy`
- `toggle`
- `logs`

Findings:
- No `$this->authorize()`
- No `authorizeResource()`
- No role middleware on routes
- Uses `AutomationRequest`, but that request defines rules only and does not restrict by role

Conclusion:
- All automation endpoints are backend-open to any authenticated tenant user.

Affected routes:
- `GET /api/v1/automations`
- `POST /api/v1/automations`
- `PUT /api/v1/automations/{automation}`
- `DELETE /api/v1/automations/{automation}`
- `PATCH /api/v1/automations/{automation}/toggle`
- `GET /api/v1/automations/{automation}/logs`

Risk:
- Any agent can create, edit, disable, delete, and inspect automations for the tenant.

## 2. ContactController

File: `app/Http/Controllers/Api/V1/ContactController.php`

Methods reviewed:
- `index`
- `store`
- `show`
- `update`
- `destroy`
- `merge`
- `duplicates`
- `tags`

Findings:
- No `$this->authorize()`
- No `authorizeResource()`
- No role middleware on routes
- `StoreContactRequest::authorize()` returns `true`
- `ContactRequest::authorize()` returns `true`

Conclusion:
- All contact endpoints are backend-open to any authenticated tenant user.

Affected routes:
- `GET /api/v1/contacts`
- `POST /api/v1/contacts`
- `GET /api/v1/contacts/{contact}`
- `PATCH /api/v1/contacts/{contact}`
- `DELETE /api/v1/contacts/{contact}`
- `POST /api/v1/contacts/{contact}/merge`
- `GET /api/v1/contacts/duplicates`
- `GET /api/v1/contacts/tags`

Risk:
- Any tenant user can create, update, delete, and merge contacts.
- Any tenant user can inspect duplicate-detection results and tag inventory.

Note:
- There is a tenant check inside `merge`, but it only validates tenant ownership of both contacts. It does not restrict by role.

## 3. QuickReplyController

File: `app/Http/Controllers/Api/V1/QuickReplyController.php`

Methods reviewed:
- `index`
- `store`
- `update`
- `destroy`

Findings:
- No `$this->authorize()`
- No `authorizeResource()`
- No role middleware on routes
- `QuickReplyRequest::authorize()` returns `true`

Conclusion:
- All quick-reply endpoints are backend-open to any authenticated tenant user.

Affected routes:
- `GET /api/v1/quick-replies`
- `POST /api/v1/quick-replies`
- `PUT /api/v1/quick-replies/{quickReply}`
- `DELETE /api/v1/quick-replies/{quickReply}`

Risk:
- Any agent can create, modify, or delete tenant quick replies.

## 4. DashboardController

File: `app/Http/Controllers/DashboardController.php`

Method reviewed:
- `__invoke`

Findings:
- No `$this->authorize()`
- No policy
- No role middleware
- No Form Request

Conclusion:
- Dashboard access is role-open to any authenticated, verified, active tenant user.

Important nuance:
- I did not find a frontend-only restriction here.
- The dashboard is visible in the main navigation to all users.

Route:
- `GET /dashboard`

Risk:
- If the intended design was role-based dashboard visibility, the backend does not enforce that.

## Endpoints With Frontend-Only Restriction

These endpoints appear restricted in the UI but not on the backend.

## WhatsApp management

UI file:
- `resources/js/Pages/Settings/WhatsApp.tsx`

Frontend behavior:
- `canManage = auth.user.role === 'owner' || auth.user.role === 'admin'`
- Connect/disconnect buttons render only for owner/admin in the UI

Backend reality:
- Routes are only behind `auth:sanctum` and `tenant`
- Controller methods `connect` and `disconnect` do not check role

Affected routes:
- `POST /api/v1/whatsapp/connect`
- `POST /api/v1/whatsapp/disconnect`

Conclusion:
- This is a true frontend-only restriction.
- Any authenticated tenant user can call these endpoints directly.

## Endpoints With No RBAC Gate At All

These are not merely frontend-protected. They are generally accessible in both UI and backend to any authenticated tenant user.

### Settings/admin-like resources

- `AutomationController`
- `QuickReplyController`
- `AssignmentRuleController`
- contact data-quality operations under `ContactController`

### Team/metrics visibility

- `TeamController`
- `MetricsController`

### Pipeline and conversation operations

- `PipelineDealController`
- `ConversationController`
- `MessageController`

In all of these, tenant isolation exists, but owner/admin/agent restrictions do not.

## Additional Controller Notes

## AssignmentRuleController

Findings:
- No controller authorization
- `AssignmentRuleRequest::authorize()` returns `true`
- No route role middleware

Conclusion:
- Any authenticated tenant user can manage assignment rules.

## PipelineDealController

Findings:
- No controller authorization
- `PipelineDealRequest` does not implement `authorize()`
- `UpdateDealStageRequest` does not implement `authorize()`
- No route role middleware

Conclusion:
- Any authenticated tenant user can create, update, delete, view, and move deals across stages.

## ConversationController

Findings:
- No controller authorization
- `AssignConversationRequest::authorize()` returns `true`
- No route role middleware

Conclusion:
- Any authenticated tenant user can assign, close, or reopen conversations within the tenant.

## MessageController

Findings:
- No controller authorization
- `SendMessageRequest::authorize()` returns `true`
- `SendMediaMessageRequest::authorize()` returns `true`
- No route role middleware

Conclusion:
- Any authenticated tenant user can send messages and media for tenant conversations.

## TeamController and MetricsController

Findings:
- No controller authorization
- No policy
- No route role middleware

Conclusion:
- Any authenticated tenant user can access team membership, workload, and agent metrics.

## Route and UI Exposure Notes

### Settings UI is broadly exposed

`resources/js/Pages/Settings/Index.tsx` renders links for:
- WhatsApp
- Assignment rules
- Quick replies
- Automations
- Data quality

There is no role guard in that page.

### Main app navigation is broadly exposed

`resources/js/Layouts/AppLayout.tsx` renders navigation entries for:
- Dashboard
- Inbox
- Contacts
- Pipeline
- Team
- Settings

There is no role-based hiding in the main sidebar.

## Alignment Gap With Project Docs

The codebase documentation suggests RBAC exists or is intended:
- `docs/ARCHITECTURE.md` describes `RBAC: owner > admin > agent`
- `docs/AGENTS.md` lists role-based access as a backend responsibility
- `docs/ROADMAP.md` says manual reassignment is by admin

The current controller and route implementation does not enforce that RBAC model for the audited endpoints.

## Final Verdict

For the requested checks:

### Check 1: `$this->authorize()` or `authorizeResource()`

Result:
- Not present in the audited business controllers

### Check 2: Form Request `authorize()` restricted by role

Result:
- Not present for the audited business endpoints
- Existing Form Requests either return `true` or omit `authorize()`

### Check 3: route role middleware

Result:
- Not present

## Highest-priority flagged endpoints

### Frontend-only restriction

- `POST /api/v1/whatsapp/connect`
- `POST /api/v1/whatsapp/disconnect`

### Backend-open administrative resources

- all `AutomationController` endpoints
- all `QuickReplyController` endpoints
- all `AssignmentRuleController` endpoints
- all mutating `ContactController` endpoints, plus `merge` and `duplicates`

### Backend-open operational resources

- conversation assignment/close/reopen
- message send/media send/quick-reply send
- pipeline deal CRUD/stage changes
- team workload and metrics

## Recommendation Direction

This file is analysis only. No code changes were made.

Recommended remediation approach:
- define the intended RBAC matrix for owner/admin/agent
- enforce it in backend policies or route middleware, not only in the UI
- update Form Request `authorize()` only where request-level authorization is appropriate
- reserve frontend hiding for UX, never as the primary gate

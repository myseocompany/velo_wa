# Audit: `no_response_timeout` execution flow

Date: 2026-03-14

Scope:
- `app/Jobs/ProcessNoResponseTimeout.php`
- `app/Services/AutomationEngineService.php`
- `app/Models/AutomationLog.php`
- `database/migrations/2026_03_13_000001_create_automation_logs_table.php`
- `database/migrations/2026_03_14_000001_add_no_response_timeout_uniqueness_to_automation_logs_table.php`

## Findings

### 1. High: outbound replies do not clear the timeout condition

The timeout flow still depends on `conversation.first_response_at`, but the local outbound send path never sets that field.

Evidence:
- `app/Jobs/ProcessNoResponseTimeout.php:39-42` selects conversations with `first_response_at IS NULL`.
- `app/Services/AutomationEngineService.php:168-176` uses the same condition in `hasTimedOut()`.
- `app/Http/Controllers/Api/V1/MessageController.php:38-55`, `app/Http/Controllers/Api/V1/MessageController.php:76-97`, and `app/Http/Controllers/Api/V1/MessageController.php:111-128` create outbound messages but only update `last_message_at`.
- `app/Jobs/SendWhatsAppMessage.php:82-85` updates message status and `wa_message_id`, but not the conversation.
- `app/Actions/WhatsApp/StoreInboundMessage.php:29-37` exits early when the webhook echo sees the same `wa_message_id`, so the `first_response_at` update at `app/Actions/WhatsApp/StoreInboundMessage.php:60-63` never runs for locally-created outbound messages.

Impact:
- A conversation can still look "unanswered" after an agent reply.
- The new `automation_logs` guard prevents the same automation from firing every minute, but it does not fix false-positive timeout eligibility after a real reply.

Recommendation:
- If any outbound reply should stop the timeout, set `first_response_at` synchronously on the first local outbound send.
- If only human replies should stop the timeout, keep `first_response_at` for Dt1 and use a separate conversation-level timeout flag or a dedicated `automation_logs`/message-exists predicate for the timeout guard.

### 2. Medium: the original repeated-fire bug came from missing idempotency and broad redispatch; the current WIP mostly fixes that

The scheduler runs every minute, and before the current WIP there was no durable once-only marker for `no_response_timeout`. As long as `first_response_at` stayed `NULL`, the same conversation remained eligible on every tick. The old scheduler path also called the engine's broad `dispatch()` method from inside the per-automation loop, which multiplied executions when more than one timeout automation existed. The current worktree changes this to the narrower `dispatchAutomation()` path and adds an `automation_logs` claim/check, which is the correct direction.

Evidence in the current worktree:
- `routes/console.php:17-18` schedules `ProcessNoResponseTimeout` every minute.
- `app/Jobs/ProcessNoResponseTimeout.php:45-62` preloads prior `success` logs and dispatches the specific automation instead of all timeout automations.
- `app/Services/AutomationEngineService.php:62-68` reserves a timeout execution with a `processing` log.
- `app/Services/AutomationEngineService.php:77-97` finalizes the reserved log as `success` or `failed`.
- `app/Services/AutomationEngineService.php:300-338` catches unique-constraint violations so duplicate claims are dropped.
- `database/migrations/2026_03_14_000001_add_no_response_timeout_uniqueness_to_automation_logs_table.php:19-23` enforces uniqueness for `(automation_id, conversation_id)` while the timeout execution is `processing` or already `success`.

Recommendation:
- Keep the current `automation_logs`-based claim/check. It is the right fix for the repeated-fire bug.
- If the product requirement is strict "at most once per conversation", include the timeout trigger type in every read path that checks prior executions and avoid any code path that can recreate the side effect after a partial failure.

### 3. Medium: the scheduler's preload query can suppress valid runs after an automation is repurposed

The fast-path preload in `ProcessNoResponseTimeout` checks only `automation_id + status=success`; it does not filter by `trigger_type`.

Evidence:
- `app/Jobs/ProcessNoResponseTimeout.php:46-50` loads all `success` logs for the automation ID, regardless of trigger type.
- `app/Services/AutomationEngineService.php:288-297` writes logs for all trigger types, not only `no_response_timeout`.

Impact:
- If an existing automation record is edited from `keyword` or `outside_hours` into `no_response_timeout`, old success logs for the same `automation_id` can cause timeout executions to be skipped even though the timeout follow-up never ran.

Recommendation:
- Add `->where('trigger_type', AutomationTriggerType::NoResponseTimeout->value)` to the preload query so it matches the unique-index predicate.

## Wiring Check

`AutomationLog` is wired into the execution flow in the current branch state:

- `app/Models/AutomationLog.php` defines the model used by the engine.
- `app/Models/Automation.php:44-47` exposes the `logs()` relation.
- `app/Services/AutomationEngineService.php:270-338` creates, updates, and claims `automation_logs` records during execution.
- `app/Http/Controllers/Api/V1/AutomationController.php:70-77` returns logs for an automation.
- `routes/api.php:95-100` exposes `GET /api/v1/automations/{automation}/logs`.
- `app/Http/Resources/AutomationLogResource.php:12-23` serializes the log payload for the UI.

Database status in the local PostgreSQL environment:
- `php artisan migrate:status --no-interaction` reports both `2026_03_13_000001_create_automation_logs_table` and `2026_03_14_000001_add_no_response_timeout_uniqueness_to_automation_logs_table` as `Ran`.

Verification:
- `php artisan test tests/Feature/ProcessNoResponseTimeoutTest.php` passes.
- The focused tests cover the new `automation_logs` guard, but they do not cover the `first_response_at` gap described above.

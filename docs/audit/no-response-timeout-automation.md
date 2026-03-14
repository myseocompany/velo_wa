# Audit: `no_response_timeout` Repeats on Every Scheduler Tick

Date: 2026-03-14

## Scope

- `app/Jobs/ProcessNoResponseTimeout.php`
- `app/Services/AutomationEngineService.php`
- `app/Models/AutomationLog.php`
- `database/migrations/2026_03_13_000001_create_automation_logs_table.php`

## Summary

The repeated follow-up behavior is caused by a mismatch between the scheduler job and the automation engine API.

`ProcessNoResponseTimeout` iterates one timeout automation at a time, but it calls `AutomationEngineService::dispatch()`, and that method re-queries and executes all active automations for the same trigger and conversation. As a result, when one pending timeout automation is still eligible, the engine can re-run another timeout automation that already fired successfully for that same conversation.

There is also no atomic idempotency guard. The job does a read-side check against `automation_logs`, but that check is outside any lock, reservation, or uniqueness constraint. If scheduler ticks overlap or multiple workers process the same conversation close together, both can pass the pre-check before a success log exists.

## Detailed Findings

### 1. Scheduler job calls a broad execution method

In `app/Jobs/ProcessNoResponseTimeout.php`, the loop is scoped to one `$automation`, but the call is:

```php
$engine->dispatch($conversation, AutomationTriggerType::NoResponseTimeout);
```

That method in `app/Services/AutomationEngineService.php` does this:

```php
$automations = Automation::query()
    ->withoutGlobalScope('tenant')
    ->where('tenant_id', $conversation->tenant_id)
    ->where('trigger_type', $trigger->value)
    ->where('is_active', true)
    ->orderBy('priority')
    ->get();
```

Then it executes every matching automation.

Effect:

- The scheduler may decide automation `B` still needs to fire.
- While processing `B`, the engine re-loads automation `A`.
- If `A` still matches the trigger conditions, it is executed again even if the scheduler had skipped it because `A` already had a success log.

This is the primary functional bug.

### 2. Existing `automation_logs` check is not atomic

`ProcessNoResponseTimeout` preloads successful executions like this:

```php
$alreadyFired = AutomationLog::query()
    ->withoutGlobalScopes()
    ->where('automation_id', $automation->id)
    ->where('status', 'success')
    ->pluck('conversation_id')
    ->flip();
```

This avoids some duplicate sends in a single pass, but it is only a read-before-write check.

Problems:

- It does not protect against overlapping scheduler executions.
- It does not protect against two workers selecting the same conversation before either writes a success log.
- The `automation_logs` table, as created by `2026_03_13_000001_create_automation_logs_table.php`, has no uniqueness constraint enforcing one timeout execution per `(automation_id, conversation_id)`.

### 3. `AutomationLog` is wired into the execution flow

`app/Models/AutomationLog.php` is correctly connected as an Eloquent model and is already used by the engine.

`AutomationEngineService::writeLog()` writes rows into `automation_logs` with:

- `tenant_id`
- `automation_id`
- `conversation_id`
- `trigger_type`
- `action_type`
- `status`
- `error_message`
- `triggered_at`

`ProcessNoResponseTimeout` also reads from `automation_logs` to skip previously successful conversations.

Conclusion:

- `AutomationLog` is already part of the runtime flow.
- The issue is not missing wiring.
- The issue is insufficient idempotency and the wrong service entry point.

### 4. Migration wiring is present, but schema guarantees are insufficient

`database/migrations/2026_03_13_000001_create_automation_logs_table.php` creates the `automation_logs` table and is structurally valid for logging.

What it does not provide:

- No unique index for one timeout execution per conversation.
- No status for an in-progress reservation such as `processing`.
- No database-level guard against duplicate timeout sends.

So the migration is wired in, but it is not enough on its own to enforce exactly-once behavior.

## Root Cause

The root cause is a combination of:

1. `ProcessNoResponseTimeout` selecting one automation but invoking a service method that executes all timeout automations for that conversation.
2. Lack of an atomic idempotency mechanism around timeout execution.

The first issue explains why follow-ups can repeat even when logs exist for another automation.
The second issue explains why duplicates can still happen under overlap or concurrency.

## Recommended Fix

Use `automation_logs` as the idempotency source of truth and change the execution boundary.

### Required changes

1. Add a method such as `dispatchAutomation(Automation $automation, Conversation $conversation, ...)` in `AutomationEngineService`.
   - This method should execute only the automation passed in.
   - `ProcessNoResponseTimeout` should call this method instead of the broad `dispatch()` method.

2. Add an atomic reservation before sending the timeout follow-up.
   - Insert an `automation_logs` row with a status like `processing`.
   - Protect it with a unique index on `(automation_id, conversation_id)` for timeout rows in `processing` or `success`.
   - If the insert fails because the row already exists, skip execution.

3. After execution:
   - Update the reserved row to `success` on completion.
   - Update it to `failed` on error.
   - If the trigger no longer matches after reservation, remove or release the reservation cleanly.

### Why this approach is preferable

- It uses an existing table already present in the execution flow.
- It prevents both logical duplicates and concurrency duplicates.
- It keeps timeout idempotency per conversation and per automation, which matches the intended business rule.

## Proposed Schema Guard

Recommended constraint:

- Unique partial index on `(automation_id, conversation_id)`
- Only for rows where:
  - `trigger_type = 'no_response_timeout'`
  - `status IN ('processing', 'success')`

This allows:

- One in-flight timeout execution
- One successful timeout execution
- Retries after a `failed` status, if desired

## Expected Outcome After Fix

For a given conversation and a given `no_response_timeout` automation:

- The follow-up is sent exactly once.
- Later scheduler ticks do not send it again.
- Overlapping workers do not send duplicates.
- Other trigger types can continue to use the broader `dispatch()` path if needed.

## Notes

This document records analysis only. It does not describe any new code changes made in this step.

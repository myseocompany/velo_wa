# Fix Summary: Canonical `case_insensitive` Trigger Flag

Date: 2026-03-14

## Scope

Standardized the keyword automation trigger contract on `trigger_config.case_insensitive` and added backward compatibility for older `case_sensitive` payloads and stored rows.

## Changes

### 1. Request compatibility layer

Updated `app/Http/Requests/Api/AutomationRequest.php` to:

- add `prepareForValidation()`
- map incoming `trigger_config.case_sensitive` to canonical `trigger_config.case_insensitive` when the canonical key is absent
- validate `trigger_config.case_insensitive` as `nullable|boolean`

### 2. Engine keyword matching

Updated `app/Services/AutomationEngineService.php` to:

- read `$config['case_insensitive'] ?? true`
- default keyword matching to case-insensitive behavior
- lowercase both message body and keywords only when `case_insensitive` is enabled

### 3. Model read normalization

Updated `app/Models/Automation.php` to:

- add a `triggerConfig` accessor using `Attribute`
- normalize legacy `case_sensitive` data into `case_insensitive` on read
- remove the legacy key from the served payload when normalization occurs

### 4. Frontend form contract

Updated `resources/js/Pages/Settings/Automations.tsx` to:

- rename local state from `caseSensitive` to `caseInsensitive`
- bind the checkbox directly with `checked={caseInsensitive}`
- send `case_insensitive` in the trigger payload
- update the `TriggerConfig` TypeScript interface to use `case_insensitive`

## Verification

Executed:

```bash
php artisan test --filter=AutomationEngineTest
```

Result:

```text
INFO  No tests found.
```

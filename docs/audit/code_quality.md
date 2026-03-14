## Summary

I reviewed the recently modified Laravel 11 files listed in the request, with extra spot-checks on the related FormRequest classes and tenant scope implementation to confirm whether route-model binding and global scopes were actually protecting the code paths.

The main risks are in automation configuration and execution. The current code accepts invalid automation payloads, can assign conversations to arbitrary user UUIDs without proving tenant ownership, and has a non-atomic WhatsApp contact upsert path that can fail under concurrent webhook delivery. I did not find a clear N+1 issue in the reviewed endpoints; most list endpoints already eager-load the relations they serialize.

## Issues Found

- High: Automation agent assignment is not tenant-scoped and can write arbitrary UUIDs into `assigned_to`.
  File refs: `app/Http/Requests/Api/AutomationRequest.php:35`, `app/Http/Controllers/Api/V1/AutomationController.php:27-35`, `app/Http/Controllers/Api/V1/AutomationController.php:43-50`, `app/Services/AutomationEngineService.php:223-233`
  Why it matters: `action_config.agent_id` is only validated as a UUID, then stored as-is and later written directly to `conversations.assigned_to`. A malicious or mistaken client can save an automation that points at a nonexistent user or at a user ID from another tenant. In a single-DB tenancy model, this is a real tenant-boundary failure.

- Medium: Automation payload validation is too permissive, so broken automations are stored and then silently no-op at runtime.
  File refs: `app/Http/Requests/Api/AutomationRequest.php:17-43`, `app/Services/AutomationEngineService.php:199-201`, `app/Services/AutomationEngineService.php:251-255`
  Why it matters: the request does not require `action_config.message` for `send_message`, does not require tenant-valid `agent_id` for `assign_agent`, and does not validate `action_config.stage` against `DealStage`. The service then quietly returns when message/stage is invalid, so operators get bad data instead of a validation error.

- Medium: Contact merge validation is not tenant-scoped, which allows cross-tenant UUID probing and splits tenancy rules between validation and controller logic.
  File refs: `app/Http/Controllers/Api/V1/ContactController.php:107-115`
  Why it matters: `exists:contacts,id` checks the whole table, not the current tenant. A foreign-tenant contact ID passes validation, then `Contact::findOrFail()` runs under the current tenant scope and returns 404. That creates an observable difference between “UUID exists in another tenant” and “UUID does not exist at all”.

- Medium: WhatsApp contact create/link logic is a multi-step upsert without a transaction or locking.
  File refs: `app/Actions/WhatsApp/CreateOrUpdateContact.php:22-59`
  Why it matters: the code does two reads and then either updates or inserts. Under concurrent webhook delivery for the same WhatsApp JID, both requests can miss the existing row and attempt the insert path. The unique index on `(tenant_id, wa_id)` prevents duplicate rows, but the loser still gets a database exception instead of a clean retry/read-after-conflict flow. The phone-based fallback path is also unlocked, so manual-contact linking can race.

- Low: `DashboardController` uses raw request input in the cache key before normalizing the range value.
  File refs: `app/Http/Controllers/DashboardController.php:17-29`
  Why it matters: `GetDashboardStats` later sanitizes the range, but the cache key is already built from the unsanitized request value. An authenticated user can create pointless cache fragmentation with arbitrary `range` strings even though all invalid values collapse to the same logical range downstream.

## Proposed Fix

Use conditional, tenant-aware validation for automation payloads, and fail closed in the execution layer.

```php
use App\Enums\AutomationActionType;
use App\Enums\DealStage;
use Illuminate\Validation\Rule;

$tenantId = $this->user()->tenant_id;
$actionType = $this->input('action_type');

'action_config.message' => [
    Rule::requiredIf($actionType === AutomationActionType::SendMessage->value),
    'nullable',
    'string',
    'max:4096',
],
'action_config.agent_id' => [
    Rule::requiredIf($actionType === AutomationActionType::AssignAgent->value),
    'nullable',
    'uuid',
    Rule::exists('users', 'id')->where(fn ($q) => $q
        ->where('tenant_id', $tenantId)
        ->where('is_active', true)),
],
'action_config.stage' => [
    Rule::requiredIf($actionType === AutomationActionType::MoveStage->value),
    'nullable',
    Rule::enum(DealStage::class),
],
```

In `AutomationEngineService`, enforce tenant consistency even if a caller bypasses the normal list query, and resolve the agent through a tenant-scoped lookup before updating the conversation.

```php
if ($automation->tenant_id !== $conversation->tenant_id) {
    throw new \LogicException('Automation and conversation tenant mismatch.');
}

$agent = User::query()
    ->whereKey($agentId)
    ->where('is_active', true)
    ->first();

if (! $agent) {
    throw ValidationException::withMessages([
        'action_config.agent_id' => 'Invalid agent for this tenant.',
    ]);
}

$conversation->update([
    'assigned_to' => $agent->id,
    'assigned_at' => now(),
]);
```

Make merge validation tenant-aware up front.

```php
use Illuminate\Validation\Rule;

$request->validate([
    'merge_into_id' => [
        'required',
        'uuid',
        Rule::exists('contacts', 'id')->where(
            fn ($q) => $q->where('tenant_id', $request->user()->tenant_id)
        ),
        Rule::notIn([$contact->id]),
    ],
]);
```

Wrap WhatsApp contact creation/linking in a transaction and lock the candidate rows, or switch to an upsert-first flow with conflict recovery.

```php
return DB::transaction(function () use ($tenant, $waId, $phone, $waData) {
    $contact = Contact::withoutGlobalScope('tenant')
        ->where('tenant_id', $tenant->id)
        ->where(function ($q) use ($waId, $phone) {
            $q->where('wa_id', $waId)
              ->orWhere(fn ($q) => $q->whereNull('wa_id')->where('phone', $phone));
        })
        ->lockForUpdate()
        ->first();

    if ($contact) {
        $contact->update([
            'wa_id' => $contact->wa_id ?? $waId,
            'push_name' => $waData['pushName'] ?? $contact->push_name,
            'profile_pic_url' => $waData['profilePicUrl'] ?? $contact->profile_pic_url,
            'last_contact_at' => now(),
        ]);

        return $contact;
    }

    return Contact::create([
        'tenant_id' => $tenant->id,
        'wa_id' => $waId,
        'phone' => $phone,
        'source' => ContactSource::WhatsApp,
        'first_contact_at' => now(),
        'last_contact_at' => now(),
    ]);
});
```

Normalize `range` before building the dashboard cache key, ideally by validating it in a request object or by exposing a shared sanitizer instead of duplicating allowed values in the controller.

## Risk Level

Medium-High.

The tenant global scope prevents many obvious leaks in normal CRUD paths, and I did not find a strong N+1 regression in the reviewed files. The highest-risk gap is the automation assignment flow because it can persist cross-tenant or invalid user IDs into live conversation records. The WhatsApp contact upsert race is less severe from a data-isolation standpoint, but it is likely to surface as intermittent 500s under real webhook concurrency.

# Multi-line inbox — production diagnosis (consolidated)

Date: 2026-04-24 (last updated 2026-04-25)
Tenant: `019d92aa-9b2a-72a3-ad07-d59168920642` (`AMIA Ginecología`)
Affected contact: `573004410097` (`My SEO Company`)

## Status overview

| # | Issue | State |
| - | ----- | ----- |
| 1 | Second-line inbound messages routed into the default-line conversation | **Resolved** in production at `2026-04-24 21:56` (manual `horizon:terminate`). Forge Deploy Script now includes `horizon:terminate` after `$ACTIVATE_RELEASE()` (2026-04-25). |
| 2 | Inbox header shows wrong line phone for the second-line conversation | **Resolved (root cause = browser extension)**. Validated 2026-04-25 in incognito: header renders the correct line phone. No app change required. |
| A | `ConversationUpdated` did not broadcast `whatsapp_line` data | **Resolved** in commit `ed4c0df` (2026-04-24). |
| B | `assign()` / `close()` / `reopen()` dropped `whatsappLine` from response | **Resolved** in commit `ed4c0df` (2026-04-24). |
| C | `handleConnectionUpdate` overwrites `tenant.wa_phone` on every line connect | **Resolved** in commit `05ee0c6` (2026-04-25). |
| D | `MessageReceived` broadcast still missing `whatsapp_line_id` | **Resolved** in commit `1f502cc` (2026-04-25). |

---

# Issue 1 — Second-line inbound messages routed to the default-line conversation (RESOLVED)

## Tenant context

Two connected lines:

- `0ee73ef5-35f9-4f67-81a5-bc1e09cd4da6`
  - label: `Principal`
  - instance: `tenant_019d92aa`
  - phone: `573042308657`
  - is_default: `true`
- `019dc11a-d258-73c9-a636-143184aef5ea`
  - label: `nueva línea`
  - instance: `line_019dc11ad258`
  - phone: `573015627486`
  - is_default: `false`

The contact `019d933d-900c-7084-93ef-88c86ec977aa` exists once
(`phone = 573004410097`).

## What we observed

Webhooks arrived correctly for both lines:

```
2026-04-24 21:12:18  instance=tenant_019d92aa       body=cel1            (Principal)
2026-04-24 21:12:23  instance=line_019dc11ad258     body=cel2            (Nueva)
2026-04-24 21:49:11  instance=line_019dc11ad258     body=Linea2 entrada  (Nueva)
2026-04-24 21:49:18  instance=tenant_019d92aa       body=Linea 1 entrada (Principal)
```

But every inbound was persisted in the same conversation
`019d933d-9030-7096-99da-39540c5e4a7d`, tied to
`whatsapp_line_id = 0ee73ef5-…` (Principal). No conversation existed for
the second line. So second-line traffic was being merged into the Principal
thread.

## Root cause

Horizon was running stale code from the previous release.

- `readlink /proc/1283390/cwd` →
  `/home/forge/app.aricrm.co/releases/68075397`
  while `current` already pointed to `68216892` (deployed `2026-04-24 18:20`).
- In release `68075397`:
  - `app/Jobs/HandleInboundMessage.php` constructor:
    `__construct(array $payload, string $tenantId)` — no `$whatsappLineId`.
  - `app/Actions/WhatsApp/CreateOrUpdateConversation::handle(Contact, &$isNew)`
    — no line parameter; returns any open conversation for the contact.
- In release `68216892` on disk: both already accept and honor
  `$whatsappLineId`, and the partial unique index
  `unique_active_conversation_per_contact_line` enforces one active
  conversation per `(tenant, contact, line)`.

Fresh PHP-FPM workers (on `68216892`) dispatched jobs carrying
`whatsappLineId`, but the still-running Horizon master (on `68075397`)
deserialized them with the old class shape and dropped the line dimension.
The Forge Deploy Script does not include any `horizon:terminate` /
`queue:restart` step, so this was structural, not a one-off.

## Fix applied

```
ssh waterfall "cd /home/forge/app.aricrm.co/current && php artisan horizon:terminate"
```

A new master `PID 1366803` came up at `21:56` bound to
`/home/forge/app.aricrm.co/releases/68216892`.

## Post-fix verification (2026-04-24 21:57–21:58)

| timestamp | direction | instance | conversation | line |
| --- | --- | --- | --- | --- |
| 21:57:41 | in `Linea1 entrada` | `tenant_019d92aa` | `019d933d-9030-…` | Principal |
| 21:57:54 | in `Linea2 entrada` | `line_019dc11ad258` | `019dc17f-e7f3-…` (new) | Nueva línea |
| 21:58:13 | out `respuesta linea2` | — | `019dc17f-e7f3-…` | Nueva línea |
| 21:58:18 | out `respuesta linea1` | — | `019d933d-9030-…` | Principal |

The second-line conversation was created on the first inbound from that
line, which also unblocks the UI: two separate threads for the same contact,
one per line, replying inside each thread sends from that line's
`instance_id`.

## Remaining work for Issue 1

### 1.1 Add `horizon:terminate` to the Forge Deploy Script — **DONE** (2026-04-25)

Forge Deploy Script for this site now reads:

```
$CREATE_RELEASE()
cd $FORGE_RELEASE_DIRECTORY
$FORGE_COMPOSER install --no-dev --no-interaction --prefer-dist --optimize-autoloader
$FORGE_PHP artisan optimize
$FORGE_PHP artisan storage:link
$FORGE_PHP artisan migrate --force
npm ci || npm install
npm run build
$ACTIVATE_RELEASE()

$FORGE_PHP artisan horizon:terminate
$FORGE_PHP artisan reverb:restart
```

`horizon:terminate` runs after `$ACTIVATE_RELEASE()`, so the respawned
Horizon master picks up the just-activated release. `reverb:restart` does
the same for the WebSocket server. Future deploys will not reproduce the
stale-worker condition.

### 1.2 Back-fill mis-routed messages (optional, time-bounded)

**Window:** `2026-04-24 18:20` (deploy of `68216892`) → `2026-04-24 21:56`
(`horizon:terminate`). Outside this interval, routing was already correct.

Inbound rows from `line_019dc11ad258` that landed in the Principal
conversation `019d933d-9030-7096-99da-39540c5e4a7d` and should be in
`019dc17f-e7f3-70db-ab6c-df3565e2afcf`:

- `019dc156-3145-70f4-a529-c1caf82e679b` — `cel2` — `2026-04-24 21:12:23`
- `Linea2 entrada` — `2026-04-24 21:49:11` (look up id by body + timestamp)

Do **not** move outbound rows: messages sent during the stale window were
delivered through the Principal Evolution instance, so WhatsApp's view of
those messages is on the Principal line. Moving them would misrepresent what
the recipient actually saw.

Process: single transaction, then recompute `message_count`,
`first_message_at`, `last_message_at` on both conversations. Re-evaluate
`dt1_minutes_business` and assignment state if those metrics matter for this
window.

If no other tenants showed similar symptoms during the window, this is the
only data to repair. **Codex can implement this.**

### 1.3 Deploy-time safeguard (nice to have)

A post-deploy sanity check comparing
`readlink /proc/<horizon-master-pid>/cwd` against
`readlink /home/forge/app.aricrm.co/current` would catch this
silently-broken state immediately. Cheap insurance, optional. **Codex can
write the script; wiring it into the pipeline still needs panel access.**

---

# Issue 2 — Inbox header shows wrong line phone (RESOLVED — browser extension)

## Symptom

The thread header for the Nueva conversation renders the line label
correctly but the line phone is the **Principal** line's phone:

| Conversation | Expected `via …` | Actual `via …` |
| --- | --- | --- |
| `019d933d-9030-…` (Principal) | `Principal · 573042308657` | `Principal · 573042308657` ✓ |
| `019dc17f-e7f3-…` (Nueva) | `nueva línea · 573015627486` | `nueva línea · 573042308657` ✗ |

The DOM also shows extension-injected wrappers around every phone:

```
class="cc-container-hmfoachifajaajjebifbjklokdcdbjke"
```

This class comes from a third-party Chrome click-to-call extension
(extension id `hmfoachifajaajjebifbjklokdcdbjke`). It is not in the repo.

## Backend verified correct

At the time of the report (and with the post-`ed4c0df` codebase):

- `whatsapp_lines.phone` rows are correct
  (Principal → `573042308657`, Nueva → `573015627486`).
- `conversations.whatsapp_line_id` rows are correct.
- `ConversationResource` and `ConversationUpdated::broadcastWith()` both
  return the right `(label, phone)` pair per conversation.

So the wrong value is in the React state of the running browser session,
not in the response body.

## Frontend trace

`resources/js/Pages/Inbox/Index.tsx:906-911`:

```tsx
{lines.length > 1 && activeConv.whatsapp_line && (
    <p className="text-xs text-gray-400">
        via {activeConv.whatsapp_line.label}
        {activeConv.whatsapp_line.phone ? ` · ${activeConv.whatsapp_line.phone}` : ''}
    </p>
)}
```

Nothing in the JS code constructs `whatsapp_line` or merges fields between
conversations. The single source of `conv.whatsapp_line` is the API.

## Most likely cause — DOM mutation by a browser extension

The `cc-container-hmfoachifajaajjebifbjklokdcdbjke` wrappers are injected
after React renders. Some click-to-call extensions normalize phone numbers
against a configured "default outbound number" and rewrite the text node.
That can produce exactly the observed combination: React-rendered label
preserved, the phone text node replaced by the extension's chosen number.

## Validation result (2026-04-25)

Opened `https://app.aricrm.co/inbox/019dc17f-e7f3-70db-ab6c-df3565e2afcf`
in a Chrome incognito window with no extensions. The header rendered the
correct line phone for the Nueva conversation, distinct from the Principal
conversation's phone.

Diagnosis confirmed: the user-visible symptom was the click-to-call
extension `cc-container-hmfoachifajaajjebifbjklokdcdbjke` rewriting the
phone text node after React rendered. No app code change is needed for
this symptom.

## Optional UI hardening (deferred)

If a future report shows the same kind of mismatch in production-relevant
sessions, consider rendering the line label and phone in separate elements
with stable keys and adding `translate="no"` on the phone span. Not
required today.

---

# Issue C — `handleConnectionUpdate` overwrites `tenant.wa_phone` (RESOLVED)

`app/Services/WebhookHandlerService.php` (current code, line 106):

```php
if ($wuid && str_contains($wuid, '@')) {
    $lineUpdates['phone']      = explode('@', $wuid)[0];
    $tenantUpdates['wa_phone'] = $lineUpdates['phone'];
}
```

Verified live on this tenant:

- Nueva line connected `2026-04-24 22:31:42` with `wuid=573015627486@…`
  → `tenant.wa_phone` set to `573015627486`.
- Principal line connected `2026-04-24 22:53:41` with `wuid=573042308657@…`
  → `tenant.wa_phone` flipped to `573042308657`.

In a multi-line tenant, `tenant.wa_phone` is a legacy single-line field. It
currently flips to whichever line connected most recently. Anything that
still reads it (e.g. legacy fallbacks in
`app/Actions/WhatsApp/CreateOrUpdateContact::sanitizeResolvedPhone`) gets
non-deterministic results.

Implemented in commit `05ee0c6` (`Stop overwriting tenant WA phone on line connect`, 2026-04-25).

Change:

- `app/Services/WebhookHandlerService.php` no longer writes
  `$tenantUpdates['wa_phone']` inside `handleConnectionUpdate()`.
- `whatsapp_lines.phone` remains the authoritative per-line phone source.
- legacy `tenant.wa_phone` is no longer mutated opportunistically by whichever
  line connected last.

Verification:

- `tests/Feature/WebhookLineResolutionTest.php`
- `test_connection_update_does_not_overwrite_legacy_tenant_wa_phone`

No further action required unless the product wants to fully deprecate
`tenant.wa_phone` later.

---

# Issue D — `MessageReceived` still missing `whatsapp_line_id` (RESOLVED)

`app/Events/MessageReceived.php::broadcastWith()` includes `conversation_id`
but not `whatsapp_line_id`. With `ConversationUpdated` already fixed (commit
`ed4c0df`), the inbox can now resolve a conversation's line from the
preceding `ConversationUpdated` payload, so this is a smaller paper-cut than
it was before.

Implemented in commit `1f502cc` (`Include line id in message received broadcasts`, 2026-04-25).

Change:

- `app/Events/MessageReceived.php::broadcastWith()` now loads `conversation`
  and emits:
  - `conversation_id`
  - `whatsapp_line_id`

That lets the SPA attribute inbound/outbound realtime events to the correct
line even when the conversation list state is not yet fully hydrated.

Verification:

- `tests/Unit/MessageReceivedTest.php`
- `test_broadcast_includes_whatsapp_line_id`

---

# Resolved during investigation

These were flagged as adjacent bugs while diagnosing the issues above and
were fixed in commit `ed4c0df` (`Include WhatsApp line in conversation
updates`, `2026-04-24 17:36`):

- `ConversationUpdated::broadcastWith()` now eager-loads `whatsappLine` and
  emits both `whatsapp_line_id` and a `whatsapp_line` object on every
  broadcast. New conversations created on a second line therefore arrive in
  the SPA with full line metadata — sidebar badge and `via …` line render
  immediately, no list refetch required.
- `ConversationController::assign()`, `close()`, `reopen()` now include
  `whatsappLine` in their `fresh([...])` calls. After mutations,
  `setActiveConv(res.data.data)` no longer drops `activeConv.whatsapp_line`.

No further action needed for these.

---

# Codex coverage

| Item | Codex can fix? | Notes |
| --- | --- | --- |
| 1.1 Add `horizon:terminate` to Forge Deploy Script | **No** | Forge panel; needs human access. |
| 1.2 Back-fill mis-routed inbound rows (window `18:20–21:56`) | Yes | Single SQL transaction + counter recomputation. |
| 1.3 Deploy-time sanity check | Partial | Codex writes the script; wiring needs panel access. |
| Issue 2 — incognito validation | **No** | Human-side step. |
| Issue 2 — UI hardening (only if validation says it's needed) | Yes | TS edits in `Inbox/Index.tsx`. |
| Issue C — drop `tenant.wa_phone` write | **Done** | Commit `05ee0c6`. |
| Issue D — add `whatsapp_line_id` to `MessageReceived` | **Done** | Commit `1f502cc`. |

Summary: Codex has now landed **C** and **D**. Remaining optional code work is
**1.2** (bounded data back-fill) plus the script half of **1.3**. The two
human/panel-side steps were **1.1** (now done in Forge) and the incognito
validation for Issue 2 (also done).

---

# Final summary

- Issue 1 (multi-line routing): resolved. Forge Deploy Script now restarts
  Horizon after activate (2026-04-25). Optional bounded data back-fill
  (1.2). Optional safeguard (1.3).
- Issue 2 (header phone mismatch): resolved as a browser-extension issue.
  Incognito validation on 2026-04-25 confirmed the app renders the correct
  line phone. No app change required.
- Issues A and B were already fixed in commit `ed4c0df`.
- Issue C was fixed in commit `05ee0c6`.
- Issue D was fixed in commit `1f502cc`.

# Fix: Contact Upsert Race

## Scope

Updated `app/Actions/WhatsApp/CreateOrUpdateContact.php` to make the existing read-then-write contact flow transactional.

## Changes

- Wrapped the full find-or-create flow in `DB::transaction()`.
- Added `lockForUpdate()` to the initial lookup by `(tenant_id, wa_id)`.
- Added `lockForUpdate()` to the fallback lookup by `(tenant_id, phone)` where `wa_id` is `NULL`.
- Kept the existing update and create field assignments unchanged.

## Verification

Ran:

```bash
php artisan test --filter=CreateOrUpdateContactTest
```

Result: `php artisan test --filter=CreateOrUpdateContactTest` completed successfully but reported `No tests found.`

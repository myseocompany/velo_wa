## SQLite UTF-8 Search Fix

Date: 2026-03-14

Scope:
- [app/Http/Controllers/Api/V1/ContactController.php](/Users/projects/velo_wa/app/Http/Controllers/Api/V1/ContactController.php)
- [tests/Feature/ContactSearchTest.php](/Users/projects/velo_wa/tests/Feature/ContactSearchTest.php)

Changes:
- Added a defensive UTF-8 validation/conversion step in the contact search sanitization path before the `LIKE` filters are built.
- Kept the existing `iconv(..., 'UTF-8//IGNORE', ...)` scrub so malformed bytes are still dropped after conversion.
- Updated the accented-name feature test to send the `search` query parameter as explicitly URL-encoded UTF-8 instead of relying on a raw accented literal inside the request URL.

Verification:
- Command run: `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test --filter=ContactSearchTest`
- Result: pass

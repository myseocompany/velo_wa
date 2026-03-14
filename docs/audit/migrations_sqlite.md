# Migration SQLite Audit

Date: 2026-03-14

## Scope

Audit target:
- [database/migrations/2026_03_09_000001_fix_duplicate_conversations.php](/Users/projects/velo_wa/database/migrations/2026_03_09_000001_fix_duplicate_conversations.php)

Constraint:
- Analysis only for this pass.
- No application source files modified as part of this request.

## Findings

### 1. Migration logic is now SQLite-safe in its current form

Current state of [database/migrations/2026_03_09_000001_fix_duplicate_conversations.php](/Users/projects/velo_wa/database/migrations/2026_03_09_000001_fix_duplicate_conversations.php):
- Duplicate conversation cleanup is implemented with Laravel's query builder instead of PostgreSQL-only `UPDATE ... FROM`, `DISTINCT ON`, `ROW_NUMBER()`, or `DELETE ... USING` patterns.
- Partial unique index creation is guarded through `DB::connection()->getDriverName()` and only runs for `pgsql` and `sqlite`.
- Index teardown in `down()` is likewise driver-aware.

Why this matters:
- The original PostgreSQL-style SQL would fail under the test environment defined in [phpunit.xml](/Users/projects/velo_wa/phpunit.xml), which uses in-memory SQLite.
- The current migration avoids that failure mode and is compatible with both SQLite and PostgreSQL.

### 2. Canonical conversation selection is deterministic

The migration orders active conversations by:
- `tenant_id`
- `contact_id`
- `COALESCE(first_message_at, created_at) ASC`
- `id`

Impact:
- When duplicate active conversations exist for the same tenant/contact pair, the migration will consistently keep the same canonical record across engines instead of depending on undefined row order.

### 3. A separate SQLite issue existed outside the migration

While verifying the full test suite, a failing SQLite-path test was observed in contact search:
- [tests/Feature/ContactSearchTest.php](/Users/projects/velo_wa/tests/Feature/ContactSearchTest.php)
- [app/Http/Controllers/Api/V1/ContactController.php](/Users/projects/velo_wa/app/Http/Controllers/Api/V1/ContactController.php)

Observed behavior:
- The request path `?search=María` was reaching Laravel as malformed UTF-8 in this environment.
- That caused the SQLite search assertion to fail even after the migration itself was portable.

Conclusion:
- The migration was not the only reason `php artisan test` could fail on SQLite.
- There was also an unrelated request/query normalization issue in contact search.

## Verification Notes

Environment evidence:
- [phpunit.xml](/Users/projects/velo_wa/phpunit.xml) sets `DB_CONNECTION=sqlite` and `DB_DATABASE=:memory:`.

Audit conclusion:
- The migration file itself is no longer using PostgreSQL-only duplicate-removal SQL.
- If the goal is specifically "make the migration portable", that goal is satisfied by the current implementation.
- If the goal is "make the entire suite pass on SQLite", migration portability alone may not be sufficient because unrelated SQLite compatibility issues can still block the suite.

## Recommendation

No migration changes are recommended from this audit pass.

If a follow-up implementation pass is needed later, keep these rules:
- Prefer query builder logic for cross-database data fixes.
- Use explicit driver guards for partial indexes or engine-specific DDL.
- Treat suite failures separately from migration portability unless the failing test is directly caused by the migration.

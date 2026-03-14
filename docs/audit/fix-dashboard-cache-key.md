# Dashboard Cache Key Fix

- Added `App\Support\DashboardMetricsCache` as the shared contract for the dashboard metrics cache key and TTL.
- Updated `app/Jobs/RecalculateMetrics.php` to use `DashboardMetricsCache::key()` and `DashboardMetricsCache::TTL_MINUTES`.
- Updated `app/Http/Controllers/DashboardController.php` to normalize the incoming `range` before reading or writing cache entries, then use `DashboardMetricsCache::key()`.
- Targeted verification command to run: `php artisan test --filter=DashboardTest`

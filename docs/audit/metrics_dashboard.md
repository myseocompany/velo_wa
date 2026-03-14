## Summary
`RecalculateMetrics` does not write to a dedicated metrics table. It writes Laravel cache entries with the key pattern `dashboard:{tenantId}:{range}:0` and a 70-minute TTL in [app/Jobs/RecalculateMetrics.php](/Users/projects/velo_wa/app/Jobs/RecalculateMetrics.php#L29) and [app/Jobs/RecalculateMetrics.php](/Users/projects/velo_wa/app/Jobs/RecalculateMetrics.php#L52). In this workspace, those entries are stored in Redis because `CACHE_STORE=redis` in [.env](/Users/projects/velo_wa/.env#L38), with the configured prefix from [.env](/Users/projects/velo_wa/.env#L39).

`DashboardController` should read those same cache entries for the default `business_hours=false` dashboard path, and only fall back to live recomputation on cache miss. The current controller already does that in [app/Http/Controllers/DashboardController.php](/Users/projects/velo_wa/app/Http/Controllers/DashboardController.php#L23). There is no separate DB table to query for warmed dashboard metrics.

## Issues Found (with file:line references)
1. Cache contract is implicit and duplicated between the job and the controller. The key format and TTL are repeated as string literals in [app/Jobs/RecalculateMetrics.php](/Users/projects/velo_wa/app/Jobs/RecalculateMetrics.php#L29) and [app/Http/Controllers/DashboardController.php](/Users/projects/velo_wa/app/Http/Controllers/DashboardController.php#L24). That means the precompute/write path and the read path can drift independently even though they are meant to be a single contract.

2. The warmed payload depends on authenticated tenant context inside `GetDashboardStats`, not only on the injected `$user`. The action receives `User $user` in [app/Actions/Dashboard/GetDashboardStats.php](/Users/projects/velo_wa/app/Actions/Dashboard/GetDashboardStats.php#L37), but the chart queries still resolve tenant scope from `auth()->user()->tenant_id` in [app/Actions/Dashboard/GetDashboardStats.php](/Users/projects/velo_wa/app/Actions/Dashboard/GetDashboardStats.php#L250) and [app/Actions/Dashboard/GetDashboardStats.php](/Users/projects/velo_wa/app/Actions/Dashboard/GetDashboardStats.php#L287). `RecalculateMetrics` works only because it calls `Auth::login($user)` before warming the cache in [app/Jobs/RecalculateMetrics.php](/Users/projects/velo_wa/app/Jobs/RecalculateMetrics.php#L48). That coupling is easy to miss and makes the cache warmer fragile.

3. The original concern that the dashboard was always resolving metrics live is not true in the current code. For non-business-hours requests, the controller reads from `Cache::get("dashboard:{$tenantId}:{$range}:0")` first in [app/Http/Controllers/DashboardController.php](/Users/projects/velo_wa/app/Http/Controllers/DashboardController.php#L24). Live recomputation only happens on cache miss or when `business_hours=true` in [app/Http/Controllers/DashboardController.php](/Users/projects/velo_wa/app/Http/Controllers/DashboardController.php#L27).

## Proposed Fix (code snippets where applicable)
The minimal wiring change is to make both `RecalculateMetrics` and `DashboardController` use one shared cache-key contract instead of duplicating the literal. The controller should keep reading from Laravel cache, not from a database table.

Example minimal extraction:

```php
<?php

declare(strict_types=1);

namespace App\Support;

final class DashboardMetricsCache
{
    public const TTL_MINUTES = 70;

    public static function key(string $tenantId, string $range, bool $businessHours = false): string
    {
        return sprintf('dashboard:%s:%s:%d', $tenantId, $range, $businessHours ? 1 : 0);
    }
}
```

Then the job writes through that contract:

```php
$cacheKey = DashboardMetricsCache::key($user->tenant_id, $range, false);
Cache::put($cacheKey, $data, now()->addMinutes(DashboardMetricsCache::TTL_MINUTES));
```

And the controller reads through the same contract:

```php
$cacheKey = DashboardMetricsCache::key($tenantId, $range, false);
$data = Cache::get($cacheKey);

if ($data === null) {
    $data = $action->handle($request->user(), $range, false);
    Cache::put($cacheKey, $data, now()->addMinutes(DashboardMetricsCache::TTL_MINUTES));
}
```

If you want to remove the hidden `Auth::login()` dependency after that, the next step would be to make `GetDashboardStats` consistently scope by the injected `$user->tenant_id` instead of `auth()->user()`, but that is a separate hardening change, not the minimal wiring fix.

## Risk Level (High / Medium / Low)
Medium. The dashboard/cache wiring itself is present and functional today, so there is no immediate broken read path. The risk comes from the duplicated key contract and the action's hidden dependency on authenticated tenant state, which can silently break prewarmed metrics during later refactors or queue-context changes.

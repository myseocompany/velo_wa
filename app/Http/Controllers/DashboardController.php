<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Dashboard\GetDashboardStats;
use App\Support\DashboardMetricsCache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    private const ALLOWED_RANGES = [
        'horas',
        'semana',
        'mes',
        'trimestre',
        'ano',
    ];

    public function __invoke(Request $request, GetDashboardStats $action): Response
    {
        $range         = $this->normalizeRange($request->string('range', 'horas')->toString());
        $businessHours = $request->boolean('business_hours', false);
        $tenantId      = (string) $request->user()->tenant_id;

        // Serve pre-warmed cache when available (RecalculateMetrics job populates every hour).
        // Business-hours filtering is computed live as it depends on the exact request time.
        if (! $businessHours) {
            $cacheKey = DashboardMetricsCache::key($tenantId, $range, false);
            $data     = Cache::get($cacheKey);

            if ($data === null) {
                $data = $action->handle($request->user(), $range, false);
                Cache::put($cacheKey, $data, now()->addMinutes(DashboardMetricsCache::TTL_MINUTES));
            }
        } else {
            $data = $action->handle($request->user(), $range, true);
        }

        return Inertia::render('Dashboard', $data);
    }

    private function normalizeRange(string $range): string
    {
        return in_array($range, self::ALLOWED_RANGES, true) ? $range : 'semana';
    }
}

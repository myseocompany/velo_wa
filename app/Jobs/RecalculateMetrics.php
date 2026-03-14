<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Dashboard\GetDashboardStats;
use App\Models\User;
use App\Support\DashboardMetricsCache;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Pre-computes and caches dashboard stats for every active tenant.
 * Scheduled hourly via routes/console.php.
 *
 * Cache keys: dashboard:{tenantId}:{range}:0
 * TTL: 70 minutes (longer than the 1-hour schedule to avoid gaps).
 */
class RecalculateMetrics implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const RANGES = ['horas', 'semana', 'mes', 'trimestre', 'ano'];

    public int $timeout = 120;
    public int $tries   = 1;

    public function handle(GetDashboardStats $action): void
    {
        // One representative user per tenant (global scope bypassed by withoutGlobalScope)
        $users = User::withoutGlobalScope('tenant')
            ->with('tenant')
            ->where('is_active', true)
            ->select('id', 'tenant_id', 'name')
            ->get()
            ->unique('tenant_id');

        $warmed = 0;

        foreach ($users as $user) {
            Auth::login($user);

            try {
                foreach (self::RANGES as $range) {
                    $cacheKey = DashboardMetricsCache::key((string) $user->tenant_id, $range, false);
                    $data     = $action->handle($user, $range, false);
                    Cache::put($cacheKey, $data, now()->addMinutes(DashboardMetricsCache::TTL_MINUTES));
                    $warmed++;
                }
            } catch (\Throwable $e) {
                Log::warning('RecalculateMetrics: failed for tenant', [
                    'tenant_id' => $user->tenant_id,
                    'error'     => $e->getMessage(),
                ]);
            } finally {
                Auth::logout();
            }
        }

        Log::info('RecalculateMetrics: completed', [
            'tenants' => $users->count(),
            'entries' => $warmed,
        ]);
    }
}

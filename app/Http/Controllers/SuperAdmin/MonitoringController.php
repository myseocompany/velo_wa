<?php

declare(strict_types=1);

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\WaHealthLog;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class MonitoringController extends Controller
{
    public function index(Request $request): Response
    {
        $tenantSearch = trim((string) $request->query('tenant', ''));
        $status = (string) $request->query('status', 'all');
        $rangeHours = max(1, min((int) $request->integer('range_hours', 24), 24 * 30));

        $to = CarbonImmutable::now();
        $from = $to->subHours($rangeHours);

        $driver = DB::connection()->getDriverName();
        $operator = $driver === 'pgsql' ? 'ilike' : 'like';

        $baseQuery = WaHealthLog::query()
            ->join('tenants', 'tenants.id', '=', 'wa_health_logs.tenant_id')
            ->where('wa_health_logs.checked_at', '>=', $from->toDateTimeString())
            ->where('wa_health_logs.checked_at', '<=', $to->toDateTimeString());

        if ($tenantSearch !== '') {
            $baseQuery->where(function ($q) use ($tenantSearch, $operator): void {
                $q->where('tenants.name', $operator, '%' . $tenantSearch . '%')
                    ->orWhere('tenants.slug', $operator, '%' . $tenantSearch . '%');
            });
        }

        if ($status === 'healthy') {
            $baseQuery->where('wa_health_logs.is_healthy', true);
        } elseif ($status === 'unhealthy') {
            $baseQuery->where('wa_health_logs.is_healthy', false);
        }

        $logs = (clone $baseQuery)
            ->select([
                'wa_health_logs.id',
                'wa_health_logs.tenant_id',
                'tenants.name as tenant_name',
                'tenants.slug as tenant_slug',
                'wa_health_logs.instance_name',
                'wa_health_logs.state',
                'wa_health_logs.is_healthy',
                'wa_health_logs.response_ms',
                'wa_health_logs.error_message',
                'wa_health_logs.checked_at',
            ])
            ->orderByDesc('wa_health_logs.checked_at')
            ->limit(300)
            ->get()
            ->map(fn ($row) => [
                'id' => $row->id,
                'tenant_id' => $row->tenant_id,
                'tenant_name' => $row->tenant_name,
                'tenant_slug' => $row->tenant_slug,
                'instance_name' => $row->instance_name,
                'state' => $row->state,
                'is_healthy' => (bool) $row->is_healthy,
                'response_ms' => $row->response_ms !== null ? (int) $row->response_ms : null,
                'error_message' => $row->error_message,
                'checked_at' => CarbonImmutable::parse($row->checked_at)->toIso8601String(),
            ]);

        $tenantSummary = (clone $baseQuery)
            ->selectRaw('wa_health_logs.tenant_id, tenants.name as tenant_name, tenants.slug as tenant_slug')
            ->selectRaw('COUNT(*) as checks_total')
            ->selectRaw('SUM(CASE WHEN wa_health_logs.is_healthy THEN 0 ELSE 1 END) as checks_unhealthy')
            ->selectRaw('AVG(wa_health_logs.response_ms) as avg_response_ms')
            ->groupBy('wa_health_logs.tenant_id', 'tenants.name', 'tenants.slug')
            ->orderByDesc('checks_unhealthy')
            ->orderByDesc('checks_total')
            ->limit(100)
            ->get()
            ->map(function ($row) {
                $total = (int) $row->checks_total;
                $unhealthy = (int) $row->checks_unhealthy;
                $errorRate = $total > 0 ? round(($unhealthy / $total) * 100, 1) : 0.0;

                return [
                    'tenant_id' => $row->tenant_id,
                    'tenant_name' => $row->tenant_name,
                    'tenant_slug' => $row->tenant_slug,
                    'checks_total' => $total,
                    'checks_unhealthy' => $unhealthy,
                    'error_rate_pct' => $errorRate,
                    'avg_response_ms' => $row->avg_response_ms !== null ? (int) round((float) $row->avg_response_ms) : null,
                ];
            });

        $totalChecks = $tenantSummary->sum('checks_total');
        $totalUnhealthy = $tenantSummary->sum('checks_unhealthy');
        $globalErrorRate = $totalChecks > 0 ? round(($totalUnhealthy / $totalChecks) * 100, 1) : 0.0;

        return Inertia::render('SuperAdmin/Monitoring', [
            'filters' => [
                'tenant' => $tenantSearch,
                'status' => $status,
                'range_hours' => $rangeHours,
            ],
            'stats' => [
                'checks_total' => $totalChecks,
                'checks_unhealthy' => $totalUnhealthy,
                'error_rate_pct' => $globalErrorRate,
                'tenants_affected' => $tenantSummary->where('checks_unhealthy', '>', 0)->count(),
                'from' => $from->toIso8601String(),
                'to' => $to->toIso8601String(),
            ],
            'tenant_summary' => $tenantSummary->values(),
            'logs' => $logs->values(),
        ]);
    }
}


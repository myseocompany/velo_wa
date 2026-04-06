import { Head, router } from '@inertiajs/react';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout';
import { useState } from 'react';

interface Filters {
    tenant: string;
    status: 'all' | 'healthy' | 'unhealthy';
    range_hours: number;
}

interface Stats {
    checks_total: number;
    checks_unhealthy: number;
    error_rate_pct: number;
    tenants_affected: number;
    from: string;
    to: string;
}

interface TenantSummaryItem {
    tenant_id: string;
    tenant_name: string;
    tenant_slug: string;
    checks_total: number;
    checks_unhealthy: number;
    error_rate_pct: number;
    avg_response_ms: number | null;
}

interface HealthLogItem {
    id: string;
    tenant_id: string;
    tenant_name: string;
    tenant_slug: string;
    instance_name: string;
    state: string | null;
    is_healthy: boolean;
    response_ms: number | null;
    error_message: string | null;
    checked_at: string;
}

export default function Monitoring({
    filters,
    stats,
    tenant_summary,
    logs,
}: {
    filters: Filters;
    stats: Stats;
    tenant_summary: TenantSummaryItem[];
    logs: HealthLogItem[];
}) {
    const [tenant, setTenant] = useState(filters.tenant ?? '');
    const [status, setStatus] = useState(filters.status ?? 'all');
    const [rangeHours, setRangeHours] = useState(String(filters.range_hours ?? 24));

    function applyFilters(e: React.FormEvent) {
        e.preventDefault();
        router.get('/superadmin/monitoring', {
            tenant: tenant || undefined,
            status,
            range_hours: Number(rangeHours) || 24,
        }, {
            preserveScroll: true,
            preserveState: true,
        });
    }

    return (
        <SuperAdminLayout title="Monitoring">
            <Head title="Monitoring" />

            <div className="space-y-6 p-6">
                <h1 className="text-xl font-bold text-white">Monitoring Evolution API</h1>

                <form onSubmit={applyFilters} className="rounded-xl border border-gray-800 bg-gray-900 p-4">
                    <div className="grid grid-cols-1 gap-3 md:grid-cols-4">
                        <input
                            value={tenant}
                            onChange={(e) => setTenant(e.target.value)}
                            placeholder="Tenant (name o slug)"
                            className="rounded-lg border border-gray-700 bg-gray-950 px-3 py-2 text-sm text-gray-100 focus:border-amber-500 focus:outline-none"
                        />
                        <select
                            value={status}
                            onChange={(e) => setStatus(e.target.value as Filters['status'])}
                            className="rounded-lg border border-gray-700 bg-gray-950 px-3 py-2 text-sm text-gray-100 focus:border-amber-500 focus:outline-none"
                        >
                            <option value="all">Todos</option>
                            <option value="healthy">Solo OK</option>
                            <option value="unhealthy">Solo ERROR</option>
                        </select>
                        <input
                            type="number"
                            min={1}
                            max={720}
                            value={rangeHours}
                            onChange={(e) => setRangeHours(e.target.value)}
                            className="rounded-lg border border-gray-700 bg-gray-950 px-3 py-2 text-sm text-gray-100 focus:border-amber-500 focus:outline-none"
                        />
                        <button
                            type="submit"
                            className="rounded-lg bg-amber-500 px-4 py-2 text-sm font-semibold text-gray-950 hover:bg-amber-400"
                        >
                            Aplicar filtros
                        </button>
                    </div>
                </form>

                <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
                    <div className="rounded-xl border border-gray-800 bg-gray-900 p-4">
                        <p className="text-2xl font-bold text-white">{stats.checks_total.toLocaleString()}</p>
                        <p className="text-xs text-gray-500">Checks totales</p>
                    </div>
                    <div className="rounded-xl border border-gray-800 bg-gray-900 p-4">
                        <p className="text-2xl font-bold text-red-400">{stats.checks_unhealthy.toLocaleString()}</p>
                        <p className="text-xs text-gray-500">Checks con error</p>
                    </div>
                    <div className="rounded-xl border border-gray-800 bg-gray-900 p-4">
                        <p className="text-2xl font-bold text-amber-400">{stats.error_rate_pct}%</p>
                        <p className="text-xs text-gray-500">Error rate global</p>
                    </div>
                    <div className="rounded-xl border border-gray-800 bg-gray-900 p-4">
                        <p className="text-2xl font-bold text-white">{stats.tenants_affected}</p>
                        <p className="text-xs text-gray-500">Tenants afectados</p>
                    </div>
                </div>

                <div className="rounded-xl border border-gray-800 bg-gray-900 overflow-hidden">
                    <div className="border-b border-gray-800 px-5 py-3">
                        <h2 className="text-sm font-semibold text-gray-200">Resumen por tenant</h2>
                    </div>
                    <table className="w-full text-sm">
                        <thead className="bg-gray-800/50 text-xs text-gray-500 uppercase">
                            <tr>
                                <th className="px-5 py-2.5 text-left">Tenant</th>
                                <th className="px-5 py-2.5 text-right">Checks</th>
                                <th className="px-5 py-2.5 text-right">Errores</th>
                                <th className="px-5 py-2.5 text-right">Error rate</th>
                                <th className="px-5 py-2.5 text-right">Latency avg</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-800">
                            {tenant_summary.map((row) => (
                                <tr key={row.tenant_id}>
                                    <td className="px-5 py-3">
                                        <p className="font-medium text-white">{row.tenant_name}</p>
                                        <p className="text-xs text-gray-500">{row.tenant_slug}</p>
                                    </td>
                                    <td className="px-5 py-3 text-right text-gray-300">{row.checks_total}</td>
                                    <td className="px-5 py-3 text-right text-gray-300">{row.checks_unhealthy}</td>
                                    <td className="px-5 py-3 text-right">
                                        <span className={row.error_rate_pct > 5 ? 'text-red-400' : 'text-green-400'}>
                                            {row.error_rate_pct}%
                                        </span>
                                    </td>
                                    <td className="px-5 py-3 text-right text-gray-300">
                                        {row.avg_response_ms !== null ? `${row.avg_response_ms} ms` : '—'}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <div className="rounded-xl border border-gray-800 bg-gray-900 overflow-hidden">
                    <div className="border-b border-gray-800 px-5 py-3">
                        <h2 className="text-sm font-semibold text-gray-200">Registros recientes</h2>
                    </div>
                    <table className="w-full text-sm">
                        <thead className="bg-gray-800/50 text-xs text-gray-500 uppercase">
                            <tr>
                                <th className="px-5 py-2.5 text-left">Fecha</th>
                                <th className="px-5 py-2.5 text-left">Tenant</th>
                                <th className="px-5 py-2.5 text-left">Estado</th>
                                <th className="px-5 py-2.5 text-right">Latency</th>
                                <th className="px-5 py-2.5 text-left">Detalle</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-800">
                            {logs.map((log) => (
                                <tr key={log.id}>
                                    <td className="px-5 py-3 text-gray-300">
                                        {new Date(log.checked_at).toLocaleString('es-CO')}
                                    </td>
                                    <td className="px-5 py-3">
                                        <p className="font-medium text-white">{log.tenant_name}</p>
                                        <p className="text-xs text-gray-500">{log.tenant_slug}</p>
                                    </td>
                                    <td className="px-5 py-3">
                                        <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${
                                            log.is_healthy ? 'bg-green-500/20 text-green-400' : 'bg-red-500/20 text-red-400'
                                        }`}>
                                            {log.is_healthy ? 'OK' : 'ERROR'}
                                        </span>
                                    </td>
                                    <td className="px-5 py-3 text-right text-gray-300">{log.response_ms ?? '—'} ms</td>
                                    <td className="px-5 py-3 text-gray-400">{log.error_message ?? `state=${log.state ?? 'unknown'}`}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </SuperAdminLayout>
    );
}


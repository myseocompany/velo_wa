import { Head, Link, router } from '@inertiajs/react';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout';
import { useState } from 'react';

interface Admin {
    id: string;
    name: string;
}

interface AuditEntry {
    id: string;
    action: string;
    target_type: string | null;
    target_id: string | null;
    ip_address: string | null;
    metadata: Record<string, unknown> | null;
    admin: Admin | null;
    created_at: string;
}

interface PaginatedLogs {
    data: AuditEntry[];
    meta: { total: number; current_page: number; last_page: number };
    links: { prev: string | null; next: string | null };
}

const ACTION_COLORS: Record<string, string> = {
    login:              'bg-blue-500/20 text-blue-400',
    logout:             'bg-gray-500/20 text-gray-400',
    impersonate:        'bg-amber-500/20 text-amber-400',
    update_plan:        'bg-purple-500/20 text-purple-400',
    force_wa_disconnect:'bg-red-500/20 text-red-400',
    '2fa_enabled':      'bg-green-500/20 text-green-400',
    '2fa_disabled':     'bg-orange-500/20 text-orange-400',
    '2fa_verified':     'bg-teal-500/20 text-teal-400',
};

const ACTION_LABELS: Record<string, string> = {
    login:              'Inicio de sesión',
    logout:             'Cierre de sesión',
    impersonate:        'Impersonación',
    update_plan:        'Actualización de plan',
    force_wa_disconnect:'Desconexión WA',
    '2fa_enabled':      '2FA activado',
    '2fa_disabled':     '2FA desactivado',
    '2fa_verified':     '2FA verificado',
};

const ACTIONS = [
    'login', 'logout', 'impersonate', 'update_plan',
    'force_wa_disconnect', '2fa_enabled', '2fa_disabled',
];

export default function AuditLog({
    logs,
    filters,
}: {
    logs: PaginatedLogs;
    filters: { action?: string; from?: string; to?: string };
}) {
    const [action, setAction] = useState(filters.action ?? '');
    const [from, setFrom] = useState(filters.from ?? '');
    const [to, setTo] = useState(filters.to ?? '');

    const applyFilters = () => {
        router.get('/superadmin/audit', { action, from, to }, { preserveScroll: true });
    };

    const clearFilters = () => {
        setAction('');
        setFrom('');
        setTo('');
        router.get('/superadmin/audit', {}, { preserveScroll: true });
    };

    return (
        <SuperAdminLayout title="Auditoría">
            <Head title="Auditoría — Admin" />

            <div className="p-6 space-y-5">
                <div className="flex items-center justify-between">
                    <h1 className="text-xl font-bold text-white">
                        Auditoría <span className="ml-2 text-base font-normal text-gray-500">({logs.meta.total})</span>
                    </h1>
                </div>

                {/* Filters */}
                <div className="flex flex-wrap items-end gap-3">
                    <select
                        value={action}
                        onChange={e => setAction(e.target.value)}
                        className="rounded-lg border border-gray-700 bg-gray-800 px-3 py-2 text-sm text-gray-300 focus:border-amber-500 focus:outline-none"
                    >
                        <option value="">Todas las acciones</option>
                        {ACTIONS.map(a => (
                            <option key={a} value={a}>{ACTION_LABELS[a] ?? a}</option>
                        ))}
                    </select>

                    <div>
                        <label className="mb-1 block text-xs text-gray-500">Desde</label>
                        <input
                            type="date"
                            value={from}
                            onChange={e => setFrom(e.target.value)}
                            className="rounded-lg border border-gray-700 bg-gray-800 px-3 py-2 text-sm text-gray-300 focus:border-amber-500 focus:outline-none"
                        />
                    </div>
                    <div>
                        <label className="mb-1 block text-xs text-gray-500">Hasta</label>
                        <input
                            type="date"
                            value={to}
                            onChange={e => setTo(e.target.value)}
                            className="rounded-lg border border-gray-700 bg-gray-800 px-3 py-2 text-sm text-gray-300 focus:border-amber-500 focus:outline-none"
                        />
                    </div>

                    <button
                        onClick={applyFilters}
                        className="rounded-lg bg-amber-500 px-4 py-2 text-sm font-semibold text-gray-900 hover:bg-amber-400"
                    >
                        Filtrar
                    </button>

                    {(action || from || to) && (
                        <button
                            onClick={clearFilters}
                            className="rounded-lg border border-gray-700 px-4 py-2 text-sm text-gray-400 hover:bg-gray-800"
                        >
                            Limpiar
                        </button>
                    )}
                </div>

                {/* Table */}
                <div className="rounded-xl border border-gray-800 bg-gray-900 overflow-hidden">
                    <table className="w-full text-sm">
                        <thead className="bg-gray-800/50 text-xs text-gray-500 uppercase">
                            <tr>
                                <th className="px-5 py-3 text-left">Administrador</th>
                                <th className="px-5 py-3 text-left">Acción</th>
                                <th className="px-5 py-3 text-left">Objetivo</th>
                                <th className="px-5 py-3 text-left">IP</th>
                                <th className="px-5 py-3 text-right">Fecha</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-800">
                            {logs.data.length === 0 && (
                                <tr>
                                    <td colSpan={5} className="px-5 py-10 text-center text-gray-500">
                                        No hay registros de auditoría.
                                    </td>
                                </tr>
                            )}
                            {logs.data.map(entry => (
                                <tr key={entry.id} className="hover:bg-gray-800/30">
                                    <td className="px-5 py-3">
                                        <div className="flex items-center gap-2">
                                            <div className="flex h-7 w-7 items-center justify-center rounded-full bg-amber-500/20 text-xs font-bold text-amber-400">
                                                {entry.admin?.name.charAt(0).toUpperCase() ?? '?'}
                                            </div>
                                            <span className="text-gray-200">{entry.admin?.name ?? '—'}</span>
                                        </div>
                                    </td>
                                    <td className="px-5 py-3">
                                        <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${ACTION_COLORS[entry.action] ?? 'bg-gray-500/20 text-gray-400'}`}>
                                            {ACTION_LABELS[entry.action] ?? entry.action}
                                        </span>
                                    </td>
                                    <td className="px-5 py-3 text-gray-400">
                                        {entry.target_id ? (
                                            <span className="font-mono text-xs">
                                                {entry.target_type?.split('\\').pop()} · {entry.target_id.slice(0, 8)}…
                                            </span>
                                        ) : (
                                            <span className="text-gray-600">—</span>
                                        )}
                                        {entry.metadata && Object.keys(entry.metadata).length > 0 && (
                                            <details className="mt-1">
                                                <summary className="cursor-pointer text-xs text-gray-600 hover:text-gray-400">
                                                    metadata
                                                </summary>
                                                <pre className="mt-1 text-xs text-gray-500 whitespace-pre-wrap break-all max-w-xs">
                                                    {JSON.stringify(entry.metadata, null, 2)}
                                                </pre>
                                            </details>
                                        )}
                                    </td>
                                    <td className="px-5 py-3 font-mono text-xs text-gray-500">
                                        {entry.ip_address ?? '—'}
                                    </td>
                                    <td className="px-5 py-3 text-right text-xs text-gray-500">
                                        {new Date(entry.created_at).toLocaleString('es', {
                                            day: '2-digit', month: '2-digit', year: '2-digit',
                                            hour: '2-digit', minute: '2-digit',
                                        })}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {/* Pagination */}
                {logs.meta.last_page > 1 && (
                    <div className="flex justify-end gap-2">
                        {logs.links.prev && (
                            <Link href={logs.links.prev} className="rounded border border-gray-700 px-3 py-1.5 text-xs text-gray-400 hover:bg-gray-800">
                                ← Anterior
                            </Link>
                        )}
                        <span className="px-3 py-1.5 text-xs text-gray-500">
                            Pág. {logs.meta.current_page} / {logs.meta.last_page}
                        </span>
                        {logs.links.next && (
                            <Link href={logs.links.next} className="rounded border border-gray-700 px-3 py-1.5 text-xs text-gray-400 hover:bg-gray-800">
                                Siguiente →
                            </Link>
                        )}
                    </div>
                )}
            </div>
        </SuperAdminLayout>
    );
}

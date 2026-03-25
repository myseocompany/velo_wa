import AppLayout from '@/Layouts/AppLayout';
import { Head, usePage } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import { PageProps } from '@/types';
import { Activity, ChevronLeft, ChevronRight, Search } from 'lucide-react';
import axios from 'axios';

interface ActivityEntry {
    id: string;
    description: string;
    log_name: string | null;
    properties: Record<string, unknown> | null;
    causer: { id: string; name: string; avatar_url: string | null } | null;
    created_at: string;
}

interface Meta {
    total: number;
    current_page: number;
    last_page: number;
}

const LOG_LABELS: Record<string, string> = {
    contact:         'Contacto',
    conversation:    'Conversación',
    deal:            'Negocio',
    team_member_invited:    'Equipo',
    team_member_updated:    'Equipo',
    team_member_deactivated:'Equipo',
    team_member_reactivated:'Equipo',
    tenant_settings_updated:'Configuración',
};

const ACTION_LABELS: Record<string, string> = {
    created:                    'Creado',
    updated:                    'Actualizado',
    deleted:                    'Eliminado',
    team_member_invited:        'Miembro invitado',
    team_member_updated:        'Miembro actualizado',
    team_member_deactivated:    'Miembro desactivado',
    team_member_reactivated:    'Miembro reactivado',
    tenant_settings_updated:    'Configuración actualizada',
};

function formatAction(entry: ActivityEntry): string {
    return ACTION_LABELS[entry.description] ?? entry.description;
}

export default function SettingsActivityLog() {
    const { auth } = usePage<PageProps>().props;
    const isAdmin = auth.user.role === 'owner' || auth.user.role === 'admin';

    const [entries, setEntries] = useState<ActivityEntry[]>([]);
    const [meta, setMeta] = useState<Meta>({ total: 0, current_page: 1, last_page: 1 });
    const [loading, setLoading] = useState(true);
    const [page, setPage] = useState(1);
    const [logName, setLogName] = useState('');
    const [dateFrom, setDateFrom] = useState('');
    const [dateTo, setDateTo] = useState('');

    const fetchLogs = (p: number) => {
        setLoading(true);
        const params: Record<string, string | number> = { page: p };
        if (logName)  params.log_name = logName;
        if (dateFrom) params.from     = dateFrom;
        if (dateTo)   params.to       = dateTo;

        axios.get('/api/v1/activity', { params })
            .then(res => {
                setEntries(res.data.data);
                setMeta(res.data.meta);
            })
            .finally(() => setLoading(false));
    };

    useEffect(() => { fetchLogs(page); }, [page]);

    const handleSearch = () => {
        setPage(1);
        fetchLogs(1);
    };

    if (!isAdmin) {
        return (
            <AppLayout title="Registro de actividad">
                <Head title="Registro de actividad" />
                <div className="flex h-64 items-center justify-center">
                    <p className="text-gray-500">No tienes permisos para ver este registro.</p>
                </div>
            </AppLayout>
        );
    }

    return (
        <AppLayout title="Registro de actividad">
            <Head title="Registro de actividad" />

            <div className="max-w-4xl space-y-5 p-6">
                <div>
                    <h1 className="text-2xl font-semibold text-gray-900">Registro de actividad</h1>
                    <p className="mt-1 text-sm text-gray-500">
                        Auditoría de acciones realizadas por los miembros del equipo.
                    </p>
                </div>

                {/* Filters */}
                <div className="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-end">
                    <div className="w-full sm:w-auto">
                        <label className="mb-1 block text-xs font-medium text-gray-600">Categoría</label>
                        <select
                            value={logName}
                            onChange={e => setLogName(e.target.value)}
                            className="min-w-0 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-ari-500 focus:outline-none"
                        >
                            <option value="">Todas</option>
                            <option value="contact">Contactos</option>
                            <option value="conversation">Conversaciones</option>
                            <option value="deal">Negocios</option>
                        </select>
                    </div>
                    <div className="w-full sm:w-auto">
                        <label className="mb-1 block text-xs font-medium text-gray-600">Desde</label>
                        <input
                            type="date"
                            value={dateFrom}
                            onChange={e => setDateFrom(e.target.value)}
                            className="min-w-0 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-ari-500 focus:outline-none"
                        />
                    </div>
                    <div className="w-full sm:w-auto">
                        <label className="mb-1 block text-xs font-medium text-gray-600">Hasta</label>
                        <input
                            type="date"
                            value={dateTo}
                            onChange={e => setDateTo(e.target.value)}
                            className="min-w-0 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-ari-500 focus:outline-none"
                        />
                    </div>
                    <button
                        onClick={handleSearch}
                        className="flex min-h-11 w-full items-center justify-center gap-2 rounded-lg bg-ari-600 px-4 py-2 text-sm font-medium text-white hover:bg-ari-700 sm:w-auto"
                    >
                        <Search className="h-4 w-4" />
                        Buscar
                    </button>
                </div>

                {/* Table */}
                <div className="rounded-xl border border-gray-200 bg-white overflow-hidden">
                    {loading ? (
                        <div className="space-y-px">
                            {[1, 2, 3, 4, 5].map(i => (
                                <div key={i} className="flex gap-4 p-4">
                                    <div className="h-8 w-8 shrari-0 animate-pulse rounded-full bg-gray-200" />
                                    <div className="flex-1 space-y-2">
                                        <div className="h-4 w-48 animate-pulse rounded bg-gray-200" />
                                        <div className="h-3 w-32 animate-pulse rounded bg-gray-100" />
                                    </div>
                                </div>
                            ))}
                        </div>
                    ) : entries.length === 0 ? (
                        <div className="flex flex-col items-center justify-center py-16 text-gray-400">
                            <Activity className="mb-3 h-10 w-10" />
                            <p className="text-sm">Sin actividad registrada en este período.</p>
                        </div>
                    ) : (
                        <div className="-mx-4 overflow-x-auto px-4 sm:mx-0 sm:px-0">
                            <table className="w-full text-sm">
                            <thead className="bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">
                                <tr>
                                    <th className="px-4 py-3">Usuario</th>
                                    <th className="px-4 py-3">Acción</th>
                                    <th className="hidden px-4 py-3 sm:table-cell">Categoría</th>
                                    <th className="px-4 py-3">Fecha</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {entries.map(entry => (
                                    <tr key={entry.id} className="hover:bg-gray-50">
                                        <td className="px-4 py-3">
                                            <div className="flex items-center gap-2">
                                                <div className="flex h-7 w-7 items-center justify-center rounded-full bg-ari-100 text-xs font-semibold text-ari-700">
                                                    {entry.causer?.avatar_url
                                                        ? <img src={entry.causer.avatar_url} alt={entry.causer.name} className="h-7 w-7 rounded-full object-cover" />
                                                        : entry.causer?.name.charAt(0).toUpperCase() ?? '?'
                                                    }
                                                </div>
                                                <span className="text-gray-900">{entry.causer?.name ?? 'Sistema'}</span>
                                            </div>
                                        </td>
                                        <td className="px-4 py-3 text-gray-700">{formatAction(entry)}</td>
                                        <td className="hidden px-4 py-3 sm:table-cell">
                                            <span className="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600">
                                                {LOG_LABELS[entry.log_name ?? ''] ?? entry.log_name ?? '—'}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3 text-gray-500 whitespace-nowrap">
                                            {new Date(entry.created_at).toLocaleString('es', {
                                                day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit'
                                            })}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                            </table>
                        </div>
                    )}
                </div>

                {/* Pagination */}
                {meta.last_page > 1 && (
                    <div className="flex flex-col gap-3 text-sm text-gray-500 sm:flex-row sm:items-center sm:justify-between">
                        <span>{meta.total} registros</span>
                        <div className="flex items-center gap-2">
                            <button
                                onClick={() => setPage(p => Math.max(1, p - 1))}
                                disabled={page === 1 || loading}
                                className="flex h-11 w-11 items-center justify-center rounded-lg hover:bg-gray-100 disabled:opacity-40"
                            >
                                <ChevronLeft className="h-4 w-4" />
                            </button>
                            <span>Página {meta.current_page} de {meta.last_page}</span>
                            <button
                                onClick={() => setPage(p => Math.min(meta.last_page, p + 1))}
                                disabled={page === meta.last_page || loading}
                                className="flex h-11 w-11 items-center justify-center rounded-lg hover:bg-gray-100 disabled:opacity-40"
                            >
                                <ChevronRight className="h-4 w-4" />
                            </button>
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}

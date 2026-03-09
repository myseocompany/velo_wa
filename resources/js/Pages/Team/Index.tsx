import AppLayout from '@/Layouts/AppLayout';
import axios from 'axios';
import { formatDistanceToNow } from 'date-fns';
import { es } from 'date-fns/locale';
import {
    AlertTriangle,
    BarChart2,
    Clock,
    MessageSquare,
    RefreshCw,
    Users,
    Wifi,
    WifiOff,
} from 'lucide-react';
import { useEffect, useState } from 'react';

interface AgentWorkload {
    id: string;
    name: string;
    email: string;
    role: string;
    is_online: boolean;
    max_concurrent_conversations: number;
    open_conversations: number;
    pending_conversations: number;
    active_conversations: number;
    capacity_pct: number | null;
}

interface AgentPerf {
    id: string;
    name: string;
    conversations_handled: number;
    avg_dt1: number | null;
    messages_sent: number;
}

type Range = 'hoy' | 'semana' | 'mes';

const RANGE_LABELS: Record<Range, string> = {
    hoy:    'Hoy',
    semana: 'Esta semana',
    mes:    'Este mes',
};

function formatDt1(seconds: number | null): string {
    if (seconds === null) return '—';
    if (seconds < 60)   return `${seconds}s`;
    if (seconds < 3600) return `${Math.round(seconds / 60)}m`;
    return `${(seconds / 3600).toFixed(1)}h`;
}

function CapacityBar({ pct }: { pct: number | null }) {
    if (pct === null) return <span className="text-xs text-gray-400">—</span>;

    const color = pct >= 90 ? 'bg-red-500' : pct >= 70 ? 'bg-yellow-400' : 'bg-green-500';

    return (
        <div className="flex items-center gap-2">
            <div className="h-2 w-20 flex-shrink-0 overflow-hidden rounded-full bg-gray-100">
                <div className={`h-full rounded-full ${color} transition-all`} style={{ width: `${Math.min(100, pct)}%` }} />
            </div>
            <span className="text-xs text-gray-600">{pct}%</span>
        </div>
    );
}

export default function TeamIndex() {
    const [workload, setWorkload] = useState<AgentWorkload[]>([]);
    const [perf, setPerf]         = useState<AgentPerf[]>([]);
    const [loading, setLoading]   = useState(true);
    const [range, setRange]       = useState<Range>('semana');
    const [refreshedAt, setRefreshedAt] = useState<Date | null>(null);

    async function load() {
        setLoading(true);
        try {
            const [wRes, pRes] = await Promise.all([
                axios.get<{ data: AgentWorkload[] }>('/api/v1/team/workload'),
                axios.get<{ data: AgentPerf[] }>(`/api/v1/metrics/agents?range=${range}`),
            ]);
            setWorkload(wRes.data.data);
            setPerf(pRes.data.data);
            setRefreshedAt(new Date());
        } finally {
            setLoading(false);
        }
    }

    useEffect(() => { load(); }, [range]);

    // Merge workload + perf by agent id
    const merged = workload.map((w) => {
        const p = perf.find((x) => x.id === w.id);
        return { ...w, ...(p ?? { conversations_handled: 0, avg_dt1: null, messages_sent: 0 }) };
    });

    const totalActive  = workload.reduce((s, a) => s + a.active_conversations, 0);
    const totalOnline  = workload.filter((a) => a.is_online).length;
    const avgDt1       = perf.filter((p) => p.avg_dt1 !== null).map((p) => p.avg_dt1 as number);
    const globalAvgDt1 = avgDt1.length ? Math.round(avgDt1.reduce((a, b) => a + b, 0) / avgDt1.length) : null;

    return (
        <AppLayout title="Equipo">
            <div className="space-y-6 p-6">
                {/* Header */}
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold text-gray-900">Equipo</h1>
                        <p className="mt-1 text-sm text-gray-500">
                            Carga de trabajo y rendimiento de agentes
                            {refreshedAt && (
                                <span className="ml-2 text-gray-400">
                                    · actualizado {formatDistanceToNow(refreshedAt, { addSuffix: true, locale: es })}
                                </span>
                            )}
                        </p>
                    </div>

                    <div className="flex items-center gap-2">
                        {/* Range selector */}
                        <div className="flex rounded-lg border border-gray-200 bg-white text-sm overflow-hidden">
                            {(Object.keys(RANGE_LABELS) as Range[]).map((r) => (
                                <button
                                    key={r}
                                    onClick={() => setRange(r)}
                                    className={`px-3 py-1.5 font-medium transition-colors ${
                                        range === r
                                            ? 'bg-brand-600 text-white'
                                            : 'text-gray-600 hover:bg-gray-50'
                                    }`}
                                >
                                    {RANGE_LABELS[r]}
                                </button>
                            ))}
                        </div>

                        <button
                            onClick={load}
                            disabled={loading}
                            className="rounded-lg border border-gray-200 p-2 text-gray-500 hover:bg-gray-50 disabled:opacity-40"
                            title="Actualizar"
                        >
                            <RefreshCw className={`h-4 w-4 ${loading ? 'animate-spin' : ''}`} />
                        </button>
                    </div>
                </div>

                {/* Summary cards */}
                <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
                    <div className="rounded-xl border border-gray-200 bg-white p-4">
                        <div className="flex items-center gap-2 text-xs font-medium uppercase tracking-wide text-gray-500">
                            <Users className="h-3.5 w-3.5" /> Agentes online
                        </div>
                        <p className="mt-2 text-2xl font-bold text-gray-900">{totalOnline}</p>
                        <p className="text-xs text-gray-400">de {workload.length} activos</p>
                    </div>
                    <div className="rounded-xl border border-gray-200 bg-white p-4">
                        <div className="flex items-center gap-2 text-xs font-medium uppercase tracking-wide text-gray-500">
                            <MessageSquare className="h-3.5 w-3.5" /> Conversaciones activas
                        </div>
                        <p className="mt-2 text-2xl font-bold text-gray-900">{totalActive}</p>
                        <p className="text-xs text-gray-400">abiertas + pendientes</p>
                    </div>
                    <div className="rounded-xl border border-gray-200 bg-white p-4">
                        <div className="flex items-center gap-2 text-xs font-medium uppercase tracking-wide text-gray-500">
                            <Clock className="h-3.5 w-3.5" /> Dt1 promedio
                        </div>
                        <p className="mt-2 text-2xl font-bold text-brand-600">{formatDt1(globalAvgDt1)}</p>
                        <p className="text-xs text-gray-400">tiempo de primera respuesta</p>
                    </div>
                    <div className="rounded-xl border border-gray-200 bg-white p-4">
                        <div className="flex items-center gap-2 text-xs font-medium uppercase tracking-wide text-gray-500">
                            <BarChart2 className="h-3.5 w-3.5" /> Conversaciones ({RANGE_LABELS[range].toLowerCase()})
                        </div>
                        <p className="mt-2 text-2xl font-bold text-gray-900">
                            {perf.reduce((s, p) => s + p.conversations_handled, 0)}
                        </p>
                        <p className="text-xs text-gray-400">gestionadas en el período</p>
                    </div>
                </div>

                {/* Agent table */}
                <div className="overflow-hidden rounded-xl border border-gray-200 bg-white">
                    <div className="border-b border-gray-100 px-4 py-3">
                        <h2 className="text-sm font-semibold text-gray-800">Agentes</h2>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-gray-100">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Agente</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Estado</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Carga actual</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Capacidad</th>
                                    <th className="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">{RANGE_LABELS[range]}</th>
                                    <th className="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Dt1 prom.</th>
                                    <th className="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Mensajes</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {loading ? (
                                    <tr>
                                        <td colSpan={7} className="py-12 text-center">
                                            <div className="mx-auto h-6 w-6 animate-spin rounded-full border-2 border-brand-600 border-t-transparent" />
                                        </td>
                                    </tr>
                                ) : merged.length === 0 ? (
                                    <tr>
                                        <td colSpan={7} className="px-4 py-10 text-center text-sm text-gray-400">
                                            Sin agentes activos.
                                        </td>
                                    </tr>
                                ) : merged.map((agent) => {
                                    const isOverloaded = agent.capacity_pct !== null && agent.capacity_pct >= 90;
                                    return (
                                        <tr key={agent.id} className="hover:bg-gray-50">
                                            <td className="px-4 py-3">
                                                <div className="flex items-center gap-3">
                                                    <div className="relative flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full bg-brand-100 text-xs font-bold text-brand-700">
                                                        {agent.name.charAt(0).toUpperCase()}
                                                        <span className={`absolute -bottom-0.5 -right-0.5 h-2.5 w-2.5 rounded-full border-2 border-white ${agent.is_online ? 'bg-green-500' : 'bg-gray-300'}`} />
                                                    </div>
                                                    <div>
                                                        <p className="text-sm font-medium text-gray-900">{agent.name}</p>
                                                        <p className="text-xs text-gray-400">{agent.email}</p>
                                                    </div>
                                                </div>
                                            </td>
                                            <td className="px-4 py-3">
                                                {agent.is_online ? (
                                                    <span className="flex items-center gap-1 text-xs font-medium text-green-600">
                                                        <Wifi className="h-3 w-3" /> Online
                                                    </span>
                                                ) : (
                                                    <span className="flex items-center gap-1 text-xs text-gray-400">
                                                        <WifiOff className="h-3 w-3" /> Offline
                                                    </span>
                                                )}
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="flex items-center gap-2">
                                                    {isOverloaded && (
                                                        <AlertTriangle className="h-3.5 w-3.5 flex-shrink-0 text-red-500" />
                                                    )}
                                                    <div className="text-sm">
                                                        <span className="font-medium text-gray-900">{agent.active_conversations}</span>
                                                        <span className="ml-1 text-xs text-gray-400">
                                                            ({agent.open_conversations} ab. / {agent.pending_conversations} pend.)
                                                        </span>
                                                    </div>
                                                </div>
                                            </td>
                                            <td className="px-4 py-3">
                                                <CapacityBar pct={agent.capacity_pct} />
                                                {agent.max_concurrent_conversations > 0 && (
                                                    <p className="mt-0.5 text-[10px] text-gray-400">
                                                        máx. {agent.max_concurrent_conversations}
                                                    </p>
                                                )}
                                            </td>
                                            <td className="px-4 py-3 text-right text-sm font-medium text-gray-900">
                                                {agent.conversations_handled}
                                            </td>
                                            <td className="px-4 py-3 text-right text-sm font-medium text-brand-600">
                                                {formatDt1(agent.avg_dt1)}
                                            </td>
                                            <td className="px-4 py-3 text-right text-sm text-gray-700">
                                                {agent.messages_sent}
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}

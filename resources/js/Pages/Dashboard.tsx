import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { PageProps } from '@/types';
import {
    Inbox, Users, MessageSquare, Timer, CheckCheck,
    TrendingUp, Trophy, Download, ChevronRight,
} from 'lucide-react';
import {
    Bar, BarChart, CartesianGrid, ResponsiveContainer,
    Tooltip, XAxis, YAxis, Legend,
} from 'recharts';
import { useEffect, useRef, useState } from 'react';
import axios from 'axios';

// ─── Types ────────────────────────────────────────────────────────────────────

interface Stats {
    open_conversations: number;
    closed_in_period: number;
    total_contacts: number;
    messages_today: number;
    inbound_today: number;
}

interface Dt1Stats {
    avg: number | null;
    median: number | null;
    p95: number | null;
    total: number;
}

interface ChartPoint {
    label: string;
    conversations: number;
}

interface MsgPoint {
    label: string;
    inbound: number;
    outbound: number;
}

interface ConversationChart {
    range: string;
    ranges: { key: string; label: string }[];
    series: ChartPoint[];
    total: number;
}

interface MessagesChart {
    series: MsgPoint[];
    total_inbound: number;
    total_outbound: number;
}

interface PipelineSummary {
    active_deals: number;
    active_pipeline: number;
    total_won: number;
    deals_won: number;
    deals_lost: number;
}

interface RecentConv {
    id: string;
    contact_name: string;
    last_message: string;
    last_message_at: string | null;
    status: string;
}

interface AgentRow {
    id: string;
    name: string;
    conversations_handled: number;
    avg_dt1: number | null;
    messages_sent: number;
}

interface DashboardProps {
    stats: Stats;
    dt1_stats: Dt1Stats;
    conversation_chart: ConversationChart;
    messages_chart: MessagesChart;
    pipeline_summary: PipelineSummary;
    recent_conversations: RecentConv[];
}

// ─── Utils ────────────────────────────────────────────────────────────────────

function fmtDt1(seconds: number | null): string {
    if (seconds === null) return '—';
    if (seconds < 60) return `${seconds}s`;
    if (seconds < 3600) return `${Math.round(seconds / 60)}m`;
    return `${(seconds / 3600).toFixed(1)}h`;
}

function fmtValue(v: number): string {
    if (v >= 1_000_000) return `$${(v / 1_000_000).toFixed(1)}M`;
    if (v >= 1_000)     return `$${(v / 1_000).toFixed(0)}K`;
    return `$${v.toLocaleString('es-CO')}`;
}

function fmtDate(v: string | null): string {
    if (!v) return '—';
    return new Date(v).toLocaleString('es-CO', {
        day: '2-digit', month: '2-digit',
        hour: '2-digit', minute: '2-digit', hour12: false,
    });
}

// ─── Stat Card ────────────────────────────────────────────────────────────────

function StatCard({
    label, value, sub, icon, accent = 'gray',
}: {
    label: string;
    value: string | number;
    sub?: string;
    icon: React.ReactNode;
    accent?: 'blue' | 'green' | 'emerald' | 'violet' | 'orange' | 'gray';
}) {
    const bg: Record<string, string> = {
        blue: 'bg-blue-50 text-blue-600', green: 'bg-green-50 text-green-600',
        emerald: 'bg-emerald-50 text-emerald-600', violet: 'bg-violet-50 text-violet-600',
        orange: 'bg-orange-50 text-orange-600', gray: 'bg-gray-100 text-gray-500',
    };
    return (
        <div className="bg-white rounded-xl border border-gray-200 p-4 flex items-start gap-3 shadow-sm">
            <div className={`p-2 rounded-lg shrink-0 ${bg[accent]}`}>{icon}</div>
            <div className="min-w-0">
                <p className="text-xs text-gray-500 truncate">{label}</p>
                <p className="text-2xl font-bold text-gray-900 leading-tight mt-0.5">{value}</p>
                {sub && <p className="text-xs text-gray-400 mt-0.5">{sub}</p>}
            </div>
        </div>
    );
}

// ─── Range tabs ───────────────────────────────────────────────────────────────

function RangeTabs({ ranges, active, onChange }: {
    ranges: { key: string; label: string }[];
    active: string;
    onChange: (k: string) => void;
}) {
    return (
        <div className="flex gap-1 flex-wrap">
            {ranges.map(r => (
                <button key={r.key} onClick={() => onChange(r.key)}
                    className={`px-3 py-1 rounded-lg text-xs font-medium transition ${
                        active === r.key
                            ? 'bg-brand-600 text-white shadow-sm'
                            : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
                    }`}>
                    {r.label}
                </button>
            ))}
        </div>
    );
}

// ─── Agent table ──────────────────────────────────────────────────────────────

function AgentTable({ range }: { range: string }) {
    const [agents, setAgents] = useState<AgentRow[]>([]);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        setLoading(true);
        axios.get('/api/v1/metrics/agents', { params: { range } })
            .then(r => setAgents(r.data.data))
            .finally(() => setLoading(false));
    }, [range]);

    if (loading) return (
        <div className="flex items-center justify-center h-24">
            <div className="h-5 w-5 animate-spin rounded-full border-2 border-brand-500 border-t-transparent" />
        </div>
    );

    if (agents.length === 0) return (
        <p className="text-sm text-gray-400 text-center py-8">Sin datos para el período.</p>
    );

    return (
        <div className="overflow-x-auto">
            <table className="w-full text-sm">
                <thead>
                    <tr className="border-b border-gray-100">
                        <th className="text-left py-2 text-xs font-medium text-gray-500">Agente</th>
                        <th className="text-right py-2 text-xs font-medium text-gray-500">Convs.</th>
                        <th className="text-right py-2 text-xs font-medium text-gray-500">Dt1 prom.</th>
                        <th className="text-right py-2 text-xs font-medium text-gray-500">Mensajes</th>
                    </tr>
                </thead>
                <tbody className="divide-y divide-gray-50">
                    {agents.map(a => (
                        <tr key={a.id} className="hover:bg-gray-50">
                            <td className="py-2.5 font-medium text-gray-800">{a.name}</td>
                            <td className="py-2.5 text-right text-gray-700">{a.conversations_handled}</td>
                            <td className="py-2.5 text-right">
                                <span className={`text-xs font-medium px-1.5 py-0.5 rounded ${
                                    a.avg_dt1 === null ? 'text-gray-400' :
                                    a.avg_dt1 < 300 ? 'bg-emerald-50 text-emerald-700' :
                                    a.avg_dt1 < 900 ? 'bg-amber-50 text-amber-700' :
                                    'bg-rose-50 text-rose-600'
                                }`}>{fmtDt1(a.avg_dt1)}</span>
                            </td>
                            <td className="py-2.5 text-right text-gray-700">{a.messages_sent}</td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

// ─── Page ─────────────────────────────────────────────────────────────────────

export default function Dashboard({
    stats, dt1_stats, conversation_chart, messages_chart, pipeline_summary, recent_conversations,
}: DashboardProps) {
    const { auth } = usePage<PageProps>().props;
    const [range, setRange] = useState(conversation_chart.range);
    const rangeRef = useRef(range);

    function changeRange(key: string) {
        setRange(key);
        rangeRef.current = key;
        router.get(route('dashboard'), { range: key }, { preserveState: true, replace: true });
    }

    function downloadCsv(type: string) {
        window.location.href = `/api/v1/metrics/export?range=${range}&type=${type}`;
    }

    const STATUS_DOT: Record<string, string> = {
        open: 'bg-emerald-400', pending: 'bg-amber-400', closed: 'bg-gray-300',
    };

    return (
        <AppLayout title="Dashboard">
            <Head title="Dashboard" />

            <div className="p-5 space-y-5 bg-gray-50 min-h-full">

                {/* Header */}
                <div className="flex items-center justify-between gap-4 flex-wrap">
                    <div>
                        <h1 className="text-xl font-bold text-gray-900">
                            Hola, {auth.user.name.split(' ')[0]} 👋
                        </h1>
                        <p className="text-sm text-gray-500 mt-0.5">Resumen del equipo</p>
                    </div>
                    <div className="flex items-center gap-2">
                        <RangeTabs ranges={conversation_chart.ranges} active={range} onChange={changeRange} />
                        <div className="relative group">
                            <button className="p-2 rounded-lg border border-gray-200 bg-white text-gray-500 hover:text-gray-700 hover:bg-gray-50">
                                <Download size={15} />
                            </button>
                            <div className="absolute right-0 top-full mt-1 bg-white border border-gray-200 rounded-xl shadow-lg z-10 hidden group-hover:block min-w-max">
                                <button onClick={() => downloadCsv('conversations')}
                                    className="block w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50 rounded-t-xl">
                                    Exportar conversaciones
                                </button>
                                <button onClick={() => downloadCsv('agents')}
                                    className="block w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50 rounded-b-xl">
                                    Exportar agentes
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                {/* KPI row 1 — conversations */}
                <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
                    <StatCard label="Abiertas"      value={stats.open_conversations}  icon={<Inbox size={17} />}        accent="blue" />
                    <StatCard label="Cerradas (período)" value={stats.closed_in_period} icon={<CheckCheck size={17} />}  accent="emerald" />
                    <StatCard label="Contactos"     value={stats.total_contacts}       icon={<Users size={17} />}        accent="violet" />
                    <StatCard label="Mensajes hoy"  value={stats.messages_today}
                        sub={`${stats.inbound_today} entrantes`}
                        icon={<MessageSquare size={17} />} accent="orange" />
                </div>

                {/* KPI row 2 — Dt1 */}
                <div className="grid grid-cols-3 gap-3">
                    <div className="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
                        <p className="text-xs text-gray-500">Dt1 promedio</p>
                        <p className="text-2xl font-bold text-gray-900 mt-0.5">{fmtDt1(dt1_stats.avg)}</p>
                        <p className="text-xs text-gray-400 mt-1">{dt1_stats.total} convs. con respuesta</p>
                    </div>
                    <div className="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
                        <p className="text-xs text-gray-500">Dt1 mediana (P50)</p>
                        <p className="text-2xl font-bold text-gray-900 mt-0.5">{fmtDt1(dt1_stats.median)}</p>
                        <p className="text-xs text-gray-400 mt-1">50% responde en menos</p>
                    </div>
                    <div className="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
                        <p className="text-xs text-gray-500">Dt1 P95</p>
                        <p className={`text-2xl font-bold mt-0.5 ${
                            dt1_stats.p95 === null ? 'text-gray-900' :
                            dt1_stats.p95 < 600 ? 'text-emerald-600' :
                            dt1_stats.p95 < 1800 ? 'text-amber-600' : 'text-rose-600'
                        }`}>{fmtDt1(dt1_stats.p95)}</p>
                        <p className="text-xs text-gray-400 mt-1">95% responde en menos</p>
                    </div>
                </div>

                {/* Charts row */}
                <div className="grid grid-cols-1 xl:grid-cols-2 gap-4">
                    {/* Conversations chart */}
                    <div className="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
                        <div className="flex items-start justify-between gap-2 mb-4">
                            <div>
                                <h2 className="text-sm font-semibold text-gray-900">Conversaciones nuevas</h2>
                                <p className="text-xs text-gray-400 mt-0.5">{conversation_chart.total} en el período</p>
                            </div>
                            <TrendingUp size={16} className="text-gray-300 mt-0.5 shrink-0" />
                        </div>
                        <div className="h-52">
                            <ResponsiveContainer width="100%" height="100%">
                                <BarChart data={conversation_chart.series} barSize={20}>
                                    <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="#f0f0f0" />
                                    <XAxis dataKey="label" tick={{ fontSize: 11, fill: '#9ca3af' }} axisLine={false} tickLine={false} />
                                    <YAxis allowDecimals={false} tick={{ fontSize: 11, fill: '#9ca3af' }} axisLine={false} tickLine={false} width={28} />
                                    <Tooltip cursor={{ fill: '#f9fafb' }}
                                        formatter={(v: number) => [`${v} conv.`, 'Total']}
                                        contentStyle={{ borderRadius: 8, border: '1px solid #e5e7eb', fontSize: 12 }} />
                                    <Bar dataKey="conversations" fill="#f5257e" radius={[4, 4, 0, 0]} />
                                </BarChart>
                            </ResponsiveContainer>
                        </div>
                    </div>

                    {/* Messages in/out chart */}
                    <div className="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
                        <div className="flex items-start justify-between gap-2 mb-4">
                            <div>
                                <h2 className="text-sm font-semibold text-gray-900">Volumen de mensajes</h2>
                                <p className="text-xs text-gray-400 mt-0.5">
                                    {messages_chart.total_inbound} entrantes · {messages_chart.total_outbound} salientes
                                </p>
                            </div>
                            <MessageSquare size={16} className="text-gray-300 mt-0.5 shrink-0" />
                        </div>
                        <div className="h-52">
                            <ResponsiveContainer width="100%" height="100%">
                                <BarChart data={messages_chart.series} barSize={12}>
                                    <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="#f0f0f0" />
                                    <XAxis dataKey="label" tick={{ fontSize: 11, fill: '#9ca3af' }} axisLine={false} tickLine={false} />
                                    <YAxis allowDecimals={false} tick={{ fontSize: 11, fill: '#9ca3af' }} axisLine={false} tickLine={false} width={28} />
                                    <Tooltip cursor={{ fill: '#f9fafb' }}
                                        contentStyle={{ borderRadius: 8, border: '1px solid #e5e7eb', fontSize: 12 }} />
                                    <Legend wrapperStyle={{ fontSize: 11, paddingTop: 8 }} />
                                    <Bar dataKey="inbound"  name="Entrantes"  fill="#6366f1" radius={[4, 4, 0, 0]} />
                                    <Bar dataKey="outbound" name="Salientes"  fill="#10b981" radius={[4, 4, 0, 0]} />
                                </BarChart>
                            </ResponsiveContainer>
                        </div>
                    </div>
                </div>

                {/* Bottom row: agents + pipeline + recent */}
                <div className="grid grid-cols-1 xl:grid-cols-3 gap-4">

                    {/* Agent performance */}
                    <div className="xl:col-span-2 bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
                        <div className="flex items-center justify-between mb-4">
                            <div>
                                <h2 className="text-sm font-semibold text-gray-900">Rendimiento por agente</h2>
                                <p className="text-xs text-gray-400 mt-0.5">Período seleccionado</p>
                            </div>
                            <button onClick={() => downloadCsv('agents')}
                                className="flex items-center gap-1 text-xs text-gray-400 hover:text-gray-600">
                                <Download size={12} /> CSV
                            </button>
                        </div>
                        <AgentTable range={range} />
                    </div>

                    {/* Right column: pipeline + recent */}
                    <div className="space-y-4">
                        {/* Pipeline mini-summary */}
                        <div className="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
                            <div className="flex items-center justify-between mb-3">
                                <h2 className="text-sm font-semibold text-gray-900">Pipeline</h2>
                                <Link href="/pipeline" className="text-xs text-brand-600 hover:underline flex items-center gap-0.5">
                                    Ver <ChevronRight size={12} />
                                </Link>
                            </div>
                            <div className="space-y-2.5">
                                <div className="flex justify-between items-center">
                                    <span className="text-xs text-gray-500">Activo</span>
                                    <span className="text-sm font-semibold text-gray-900">{fmtValue(pipeline_summary.active_pipeline)}</span>
                                </div>
                                <div className="flex justify-between items-center">
                                    <span className="text-xs text-gray-500">Deals activos</span>
                                    <span className="text-sm text-gray-700">{pipeline_summary.active_deals}</span>
                                </div>
                                <div className="border-t border-gray-100 pt-2 flex justify-between items-center">
                                    <span className="flex items-center gap-1 text-xs text-emerald-600">
                                        <Trophy size={11} /> Ganado
                                    </span>
                                    <span className="text-sm font-semibold text-emerald-700">{fmtValue(pipeline_summary.total_won)}</span>
                                </div>
                                <div className="flex justify-between items-center">
                                    <span className="text-xs text-gray-400">Tasa de cierre</span>
                                    <span className="text-sm text-gray-700">
                                        {pipeline_summary.deals_won + pipeline_summary.deals_lost > 0
                                            ? `${Math.round(pipeline_summary.deals_won / (pipeline_summary.deals_won + pipeline_summary.deals_lost) * 100)}%`
                                            : '—'}
                                    </span>
                                </div>
                            </div>
                        </div>

                        {/* Recent conversations */}
                        <div className="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
                            <div className="flex items-center justify-between mb-3">
                                <h2 className="text-sm font-semibold text-gray-900">Recientes</h2>
                                <Link href="/inbox" className="text-xs text-brand-600 hover:underline flex items-center gap-0.5">
                                    Inbox <ChevronRight size={12} />
                                </Link>
                            </div>
                            <div className="space-y-2">
                                {recent_conversations.length === 0 && (
                                    <p className="text-xs text-gray-400 py-4 text-center">Sin conversaciones recientes</p>
                                )}
                                {recent_conversations.map(c => (
                                    <Link key={c.id} href={`/inbox?conversation=${c.id}`}
                                        className="flex items-start gap-2 p-2 rounded-lg hover:bg-gray-50 transition group">
                                        <span className={`w-1.5 h-1.5 rounded-full mt-1.5 shrink-0 ${STATUS_DOT[c.status] ?? 'bg-gray-300'}`} />
                                        <div className="min-w-0">
                                            <p className="text-xs font-medium text-gray-800 truncate">{c.contact_name}</p>
                                            <p className="text-xs text-gray-400 truncate mt-0.5">{c.last_message}</p>
                                        </div>
                                        <span className="text-[10px] text-gray-300 shrink-0 ml-auto pt-0.5">{fmtDate(c.last_message_at)}</span>
                                    </Link>
                                ))}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}

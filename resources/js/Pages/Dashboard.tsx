import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, usePage } from '@inertiajs/react';
import { Inbox, MessageSquare, TrendingUp, Users } from 'lucide-react';
import { PageProps } from '@/types';
import {
    Bar,
    BarChart,
    CartesianGrid,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';

interface DashboardStats {
    open_conversations: number;
    total_contacts: number;
    messages_today: number;
    avg_response_time: number | null;
}

interface DashboardProps {
    stats: DashboardStats;
    conversation_chart: ConversationChart;
    recent_conversations: RecentConversation[];
}

interface ConversationChartRange {
    key: string;
    label: string;
}

interface ConversationChartPoint {
    bucket_start: string;
    label: string;
    conversations: number;
}

interface ConversationChart {
    range: string;
    ranges: ConversationChartRange[];
    series: ConversationChartPoint[];
    total: number;
    timezone?: string;
}

interface RecentConversation {
    id: string;
    contact_name: string;
    last_message: string;
    last_message_at: string | null;
}

export default function Dashboard({ stats, conversation_chart, recent_conversations }: DashboardProps) {
    const { auth } = usePage<PageProps>().props;

    return (
        <AppLayout>
            <Head title="Dashboard" />

            <div className="p-6">
                <div className="mb-6">
                    <h1 className="text-2xl font-semibold text-gray-900">
                        Hola, {auth.user.name.split(' ')[0]} 👋
                    </h1>
                    <p className="mt-1 text-sm text-gray-500">
                        Aquí tienes el resumen de hoy.
                    </p>
                </div>

                {/* Metric cards */}
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <MetricCard
                        label="Conversaciones abiertas"
                        value={stats.open_conversations}
                        icon={<Inbox size={20} className="text-ink-700" />}
                        color="blue"
                    />
                    <MetricCard
                        label="Total contactos"
                        value={stats.total_contacts}
                        icon={<Users size={20} className="text-brand-500" />}
                        color="green"
                    />
                    <MetricCard
                        label="Mensajes hoy"
                        value={stats.messages_today}
                        icon={<MessageSquare size={20} className="text-brand-500" />}
                        color="purple"
                    />
                    <MetricCard
                        label="Tiempo resp. promedio"
                        value={
                            stats.avg_response_time != null
                                ? formatSeconds(stats.avg_response_time)
                                : '—'
                        }
                        icon={<TrendingUp size={20} className="text-ink-500" />}
                        color="orange"
                        isText
                    />
                </div>

                <div className="mt-6 grid grid-cols-1 gap-4 xl:grid-cols-3">
                    <div className="rounded-xl border border-gray-200 bg-white p-5 xl:col-span-2">
                        <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <h2 className="text-base font-semibold text-gray-900">
                                    Conversaciones
                                </h2>
                                <p className="text-sm text-gray-500">
                                    {conversation_chart.total} en el periodo seleccionado
                                </p>
                            </div>

                            <div className="flex flex-wrap gap-2">
                                {conversation_chart.ranges.map((item) => {
                                    const isActive = conversation_chart.range === item.key;
                                    return (
                                        <Link
                                            key={item.key}
                                            href={route('dashboard', { range: item.key })}
                                            preserveScroll
                                            className={`rounded-md border px-3 py-1.5 text-xs font-medium transition-colors ${
                                                isActive
                                                    ? 'border-brand-200 bg-brand-50 text-ink-900'
                                                    : 'border-gray-200 bg-white text-ink-900/70 hover:bg-brand-50 hover:text-ink-900'
                                            }`}
                                        >
                                            {item.label}
                                        </Link>
                                    );
                                })}
                            </div>
                        </div>

                        <div className="mt-5 h-72">
                            <ResponsiveContainer width="100%" height="100%">
                                <BarChart data={conversation_chart.series}>
                                    <CartesianGrid strokeDasharray="3 3" vertical={false} />
                                    <XAxis
                                        dataKey="label"
                                        tick={{ fontSize: 12, fill: '#6b7280' }}
                                        axisLine={false}
                                        tickLine={false}
                                    />
                                    <YAxis
                                        allowDecimals={false}
                                        tick={{ fontSize: 12, fill: '#6b7280' }}
                                        axisLine={false}
                                        tickLine={false}
                                    />
                                    <Tooltip
                                        cursor={{ fill: '#ffe8f3' }}
                                        formatter={(value: number) => [
                                            `${value} conversaciones`,
                                            'Total',
                                        ]}
                                    />
                                    <Bar
                                        dataKey="conversations"
                                        fill="#f5257e"
                                        radius={[6, 6, 0, 0]}
                                        maxBarSize={42}
                                    />
                                </BarChart>
                            </ResponsiveContainer>
                        </div>
                    </div>

                    <div className="rounded-xl border border-gray-200 bg-white p-5">
                        <h2 className="text-base font-semibold text-gray-900">
                            Ultimas conversaciones
                        </h2>
                        <p className="mt-1 text-sm text-gray-500">
                            5 conversaciones con mensajes recientes
                        </p>

                        <div className="mt-4 space-y-3">
                            {recent_conversations.length === 0 && (
                                <p className="text-sm text-gray-500">
                                    No hay conversaciones con mensajes.
                                </p>
                            )}

                            {recent_conversations.map((conversation) => (
                                <Link
                                    key={conversation.id}
                                    href={route('inbox.conversation', { conversation: conversation.id })}
                                    className="block rounded-lg border border-gray-200 p-3 transition-colors hover:bg-gray-50"
                                >
                                    <p className="truncate text-sm font-semibold text-gray-900">
                                        {conversation.contact_name}
                                    </p>
                                    <p className="mt-1 truncate text-xs text-gray-500">
                                        {conversation.last_message}
                                    </p>
                                    <p className="mt-2 text-xs text-gray-400">
                                        {formatDateTime(conversation.last_message_at)}
                                    </p>
                                </Link>
                            ))}
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}

interface MetricCardProps {
    label: string;
    value: number | string;
    icon: React.ReactNode;
    color: 'blue' | 'green' | 'purple' | 'orange';
    isText?: boolean;
}

function MetricCard({ label, value, icon, isText = false }: MetricCardProps) {
    return (
        <div className="rounded-lg border border-gray-200 bg-white p-5">
            <div className="flex items-center justify-between">
                <p className="text-sm font-medium text-gray-500">{label}</p>
                <div className="rounded-lg bg-gray-50 p-2">{icon}</div>
            </div>
            <p className={`mt-3 font-semibold text-gray-900 ${isText ? 'text-xl' : 'text-3xl'}`}>
                {value}
            </p>
        </div>
    );
}

function formatSeconds(seconds: number): string {
    if (seconds < 60) return `${seconds}s`;
    if (seconds < 3600) return `${Math.round(seconds / 60)}m`;
    return `${Math.round(seconds / 3600)}h`;
}

function formatDateTime(value: string | null): string {
    if (!value) return 'Sin fecha';

    const date = new Date(value);

    return date.toLocaleString('es-CO', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        hour12: false,
    });
}

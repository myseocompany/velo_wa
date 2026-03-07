import AppLayout from '@/Layouts/AppLayout';
import { Head, usePage } from '@inertiajs/react';
import { Inbox, MessageSquare, TrendingUp, Users } from 'lucide-react';
import { PageProps } from '@/types';

interface DashboardStats {
    open_conversations: number;
    total_contacts: number;
    messages_today: number;
    avg_response_time: number | null;
}

interface DashboardProps {
    stats: DashboardStats;
}

export default function Dashboard({ stats }: DashboardProps) {
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
                        icon={<Inbox size={20} className="text-blue-500" />}
                        color="blue"
                    />
                    <MetricCard
                        label="Total contactos"
                        value={stats.total_contacts}
                        icon={<Users size={20} className="text-green-500" />}
                        color="green"
                    />
                    <MetricCard
                        label="Mensajes hoy"
                        value={stats.messages_today}
                        icon={<MessageSquare size={20} className="text-purple-500" />}
                        color="purple"
                    />
                    <MetricCard
                        label="Tiempo resp. promedio"
                        value={
                            stats.avg_response_time != null
                                ? formatSeconds(stats.avg_response_time)
                                : '—'
                        }
                        icon={<TrendingUp size={20} className="text-orange-500" />}
                        color="orange"
                        isText
                    />
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

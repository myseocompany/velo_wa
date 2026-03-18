import { Head, Link } from '@inertiajs/react';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout';
import { Building2, MessageSquare, Users, Wifi, Contact } from 'lucide-react';

interface Stats {
    total_tenants: number;
    wa_connected: number;
    total_agents: number;
    total_contacts: number;
    total_conversations: number;
}

interface RecentTenant {
    id: string;
    name: string;
    slug: string;
    wa_status: string;
    wa_phone: string | null;
    users_count: number;
    contacts_count: number;
    conversations_count: number;
    created_at: string;
}

const WA_STATUS_COLORS: Record<string, string> = {
    connected:    'bg-green-500/20 text-green-400',
    disconnected: 'bg-gray-500/20 text-gray-400',
    qr_pending:   'bg-yellow-500/20 text-yellow-400',
    banned:       'bg-red-500/20 text-red-400',
};

export default function SuperAdminDashboard({ stats, recent_tenants }: { stats: Stats; recent_tenants: RecentTenant[] }) {
    return (
        <SuperAdminLayout title="Dashboard">
            <Head title="Admin Dashboard" />

            <div className="p-6 space-y-6">
                <h1 className="text-xl font-bold text-white">Platform Dashboard</h1>

                {/* Stats */}
                <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
                    {[
                        { label: 'Tenants',        value: stats.total_tenants,       icon: <Building2 size={18} />,     color: 'text-amber-400' },
                        { label: 'WA conectados',  value: stats.wa_connected,        icon: <Wifi size={18} />,          color: 'text-green-400' },
                        { label: 'Agentes',        value: stats.total_agents,        icon: <Users size={18} />,         color: 'text-blue-400' },
                        { label: 'Contactos',      value: stats.total_contacts.toLocaleString(),  icon: <Contact size={18} />,    color: 'text-purple-400' },
                        { label: 'Conversaciones', value: stats.total_conversations.toLocaleString(), icon: <MessageSquare size={18} />, color: 'text-rose-400' },
                    ].map(stat => (
                        <div key={stat.label} className="rounded-xl border border-gray-800 bg-gray-900 p-4">
                            <div className={`mb-2 ${stat.color}`}>{stat.icon}</div>
                            <p className="text-2xl font-bold text-white">{stat.value}</p>
                            <p className="text-xs text-gray-500">{stat.label}</p>
                        </div>
                    ))}
                </div>

                {/* Recent tenants */}
                <div className="rounded-xl border border-gray-800 bg-gray-900 overflow-hidden">
                    <div className="flex items-center justify-between border-b border-gray-800 px-5 py-3">
                        <h2 className="text-sm font-semibold text-gray-200">Tenants recientes</h2>
                        <Link href="/superadmin/tenants" className="text-xs text-amber-400 hover:underline">
                            Ver todos →
                        </Link>
                    </div>
                    <table className="w-full text-sm">
                        <thead className="bg-gray-800/50 text-xs text-gray-500 uppercase">
                            <tr>
                                <th className="px-5 py-2.5 text-left">Tenant</th>
                                <th className="px-5 py-2.5 text-left">WhatsApp</th>
                                <th className="px-5 py-2.5 text-right">Agentes</th>
                                <th className="px-5 py-2.5 text-right">Contactos</th>
                                <th className="px-5 py-2.5 text-right">Convs.</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-800">
                            {recent_tenants.map(t => (
                                <tr key={t.id} className="hover:bg-gray-800/30">
                                    <td className="px-5 py-3">
                                        <Link href={`/superadmin/tenants/${t.id}`} className="font-medium text-white hover:text-amber-400">
                                            {t.name}
                                        </Link>
                                        <p className="text-xs text-gray-500">{t.slug}</p>
                                    </td>
                                    <td className="px-5 py-3">
                                        <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${WA_STATUS_COLORS[t.wa_status] ?? 'bg-gray-700 text-gray-400'}`}>
                                            {t.wa_status}
                                        </span>
                                        {t.wa_phone && <p className="mt-0.5 text-xs text-gray-500">{t.wa_phone}</p>}
                                    </td>
                                    <td className="px-5 py-3 text-right text-gray-300">{t.users_count}</td>
                                    <td className="px-5 py-3 text-right text-gray-300">{t.contacts_count.toLocaleString()}</td>
                                    <td className="px-5 py-3 text-right text-gray-300">{t.conversations_count.toLocaleString()}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </SuperAdminLayout>
    );
}

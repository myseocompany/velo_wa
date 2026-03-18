import { Head, Link, router, useForm } from '@inertiajs/react';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout';
import { ArrowLeft, Users, MessageSquare, Contact, Briefcase, Wifi, WifiOff, UserCheck } from 'lucide-react';
import { useState } from 'react';

interface TenantDetail {
    id: string;
    name: string;
    slug: string;
    wa_status: string;
    wa_phone: string | null;
    wa_instance_id: string | null;
    wa_connected_at: string | null;
    timezone: string;
    max_agents: number | null;
    max_contacts: number | null;
    auto_close_hours: number | null;
    created_at: string;
}

interface Stats {
    users_count: number;
    active_users_count: number;
    contacts_count: number;
    conversations_count: number;
    open_conversations: number;
    deals_count: number;
}

interface Member {
    id: string;
    name: string;
    email: string;
    role: string;
    is_active: boolean;
    is_online: boolean;
    last_seen_at: string | null;
    created_at: string;
}

const WA_STATUS_COLORS: Record<string, string> = {
    connected:    'bg-green-500/20 text-green-400',
    disconnected: 'bg-gray-500/20 text-gray-400',
    qr_pending:   'bg-yellow-500/20 text-yellow-400',
    banned:       'bg-red-500/20 text-red-400',
};

const ROLE_LABELS: Record<string, string> = {
    owner: 'Propietario',
    admin: 'Administrador',
    agent: 'Agente',
};

function StatCard({ icon, label, value, sub }: { icon: React.ReactNode; label: string; value: string | number; sub?: string }) {
    return (
        <div className="rounded-xl border border-gray-800 bg-gray-900 p-4">
            <div className="mb-2 flex items-center gap-2 text-gray-500">
                {icon}
                <span className="text-xs">{label}</span>
            </div>
            <p className="text-2xl font-bold text-white">{value}</p>
            {sub && <p className="mt-0.5 text-xs text-gray-500">{sub}</p>}
        </div>
    );
}

export default function TenantShow({
    tenant,
    stats,
    members,
}: {
    tenant: TenantDetail;
    stats: Stats;
    members: Member[];
}) {
    const [impersonating, setImpersonating] = useState(false);
    const [disconnecting, setDisconnecting] = useState(false);

    const planForm = useForm({
        max_agents:   tenant.max_agents ?? '',
        max_contacts: tenant.max_contacts ?? '',
    });

    const submitPlan = (e: React.FormEvent) => {
        e.preventDefault();
        planForm.patch(`/superadmin/tenants/${tenant.id}/plan`);
    };

    const handleImpersonate = () => {
        if (!confirm(`¿Entrar como propietario de "${tenant.name}"?`)) return;
        setImpersonating(true);
        router.post(`/superadmin/tenants/${tenant.id}/impersonate`, {}, {
            onError: () => setImpersonating(false),
        });
    };

    const handleDisconnectWa = () => {
        if (!confirm('¿Desconectar la instancia de WhatsApp de este tenant?')) return;
        setDisconnecting(true);
        router.post(`/superadmin/tenants/${tenant.id}/wa/disconnect`, {}, {
            onFinish: () => setDisconnecting(false),
        });
    };

    return (
        <SuperAdminLayout title={tenant.name}>
            <Head title={`${tenant.name} — Admin`} />

            <div className="p-6 space-y-6">
                {/* Header */}
                <div className="flex items-start justify-between">
                    <div className="flex items-center gap-3">
                        <Link
                            href="/superadmin/tenants"
                            className="text-gray-500 hover:text-white"
                        >
                            <ArrowLeft size={18} />
                        </Link>
                        <div>
                            <h1 className="text-xl font-bold text-white">{tenant.name}</h1>
                            <p className="text-sm text-gray-500">slug: {tenant.slug}</p>
                        </div>
                    </div>

                    <div className="flex items-center gap-3">
                        <span className={`rounded-full px-3 py-1 text-xs font-medium ${WA_STATUS_COLORS[tenant.wa_status] ?? ''}`}>
                            {tenant.wa_status}
                        </span>

                        {tenant.wa_status === 'connected' && (
                            <button
                                onClick={handleDisconnectWa}
                                disabled={disconnecting}
                                className="flex items-center gap-1.5 rounded-lg border border-red-700 px-3 py-1.5 text-xs text-red-400 hover:bg-red-900/30 disabled:opacity-50"
                            >
                                <WifiOff size={14} />
                                {disconnecting ? 'Desconectando…' : 'Desconectar WA'}
                            </button>
                        )}

                        <button
                            onClick={handleImpersonate}
                            disabled={impersonating}
                            className="flex items-center gap-1.5 rounded-lg bg-amber-500 px-3 py-1.5 text-xs font-semibold text-gray-900 hover:bg-amber-400 disabled:opacity-50"
                        >
                            <UserCheck size={14} />
                            {impersonating ? 'Entrando…' : 'Impersonar'}
                        </button>
                    </div>
                </div>

                {/* Stats */}
                <div className="grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-6">
                    <StatCard
                        icon={<Users size={14} />}
                        label="Agentes"
                        value={stats.users_count}
                        sub={`${stats.active_users_count} activos`}
                    />
                    <StatCard
                        icon={<Contact size={14} />}
                        label="Contactos"
                        value={stats.contacts_count.toLocaleString()}
                    />
                    <StatCard
                        icon={<MessageSquare size={14} />}
                        label="Conversaciones"
                        value={stats.conversations_count.toLocaleString()}
                        sub={`${stats.open_conversations} abiertas`}
                    />
                    <StatCard
                        icon={<Briefcase size={14} />}
                        label="Deals"
                        value={stats.deals_count.toLocaleString()}
                    />
                    <StatCard
                        icon={<Wifi size={14} />}
                        label="WhatsApp"
                        value={tenant.wa_phone ?? '—'}
                        sub={tenant.wa_connected_at
                            ? `Conectado ${new Date(tenant.wa_connected_at).toLocaleDateString('es')}`
                            : undefined}
                    />
                    <StatCard
                        icon={<span className="text-xs">🌍</span>}
                        label="Zona horaria"
                        value={tenant.timezone}
                        sub={`Cierre: ${tenant.auto_close_hours ?? '—'}h`}
                    />
                </div>

                <div className="grid gap-6 lg:grid-cols-3">
                    {/* Plan limits */}
                    <div className="rounded-xl border border-gray-800 bg-gray-900 p-5">
                        <h2 className="mb-4 text-sm font-semibold text-white">Límites del plan</h2>
                        <form onSubmit={submitPlan} className="space-y-4">
                            <div>
                                <label className="mb-1 block text-xs text-gray-400">Máx. agentes</label>
                                <input
                                    type="number"
                                    min="1"
                                    value={planForm.data.max_agents}
                                    onChange={e => planForm.setData('max_agents', e.target.value)}
                                    placeholder="Sin límite"
                                    className="w-full rounded-lg border border-gray-700 bg-gray-800 px-3 py-2 text-sm text-white placeholder-gray-600 focus:border-amber-500 focus:outline-none"
                                />
                                {planForm.errors.max_agents && (
                                    <p className="mt-1 text-xs text-red-400">{planForm.errors.max_agents}</p>
                                )}
                            </div>
                            <div>
                                <label className="mb-1 block text-xs text-gray-400">Máx. contactos</label>
                                <input
                                    type="number"
                                    min="1"
                                    value={planForm.data.max_contacts}
                                    onChange={e => planForm.setData('max_contacts', e.target.value)}
                                    placeholder="Sin límite"
                                    className="w-full rounded-lg border border-gray-700 bg-gray-800 px-3 py-2 text-sm text-white placeholder-gray-600 focus:border-amber-500 focus:outline-none"
                                />
                                {planForm.errors.max_contacts && (
                                    <p className="mt-1 text-xs text-red-400">{planForm.errors.max_contacts}</p>
                                )}
                            </div>
                            <button
                                type="submit"
                                disabled={planForm.processing}
                                className="w-full rounded-lg bg-amber-500 py-2 text-sm font-semibold text-gray-900 hover:bg-amber-400 disabled:opacity-50"
                            >
                                {planForm.processing ? 'Guardando…' : 'Guardar límites'}
                            </button>
                        </form>

                        <div className="mt-4 border-t border-gray-800 pt-4 text-xs text-gray-500 space-y-1">
                            <p>Creado: {new Date(tenant.created_at).toLocaleDateString('es')}</p>
                            {tenant.wa_instance_id && <p>Instancia WA: {tenant.wa_instance_id}</p>}
                        </div>
                    </div>

                    {/* Members */}
                    <div className="lg:col-span-2 rounded-xl border border-gray-800 bg-gray-900 overflow-hidden">
                        <div className="px-5 py-3 border-b border-gray-800">
                            <h2 className="text-sm font-semibold text-white">
                                Miembros <span className="ml-1 text-gray-500 font-normal">({members.length})</span>
                            </h2>
                        </div>
                        <table className="w-full text-sm">
                            <thead className="bg-gray-800/50 text-xs text-gray-500 uppercase">
                                <tr>
                                    <th className="px-5 py-2.5 text-left">Nombre</th>
                                    <th className="px-5 py-2.5 text-left">Rol</th>
                                    <th className="px-5 py-2.5 text-left">Estado</th>
                                    <th className="px-5 py-2.5 text-right">Último acceso</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-800">
                                {members.length === 0 && (
                                    <tr>
                                        <td colSpan={4} className="px-5 py-8 text-center text-gray-500">
                                            Sin miembros.
                                        </td>
                                    </tr>
                                )}
                                {members.map(m => (
                                    <tr key={m.id} className="hover:bg-gray-800/30">
                                        <td className="px-5 py-3">
                                            <div className="flex items-center gap-2">
                                                <div className="relative">
                                                    <div className="flex h-7 w-7 items-center justify-center rounded-full bg-gray-700 text-xs font-bold text-gray-300">
                                                        {m.name.charAt(0).toUpperCase()}
                                                    </div>
                                                    {m.is_online && (
                                                        <span className="absolute -bottom-0.5 -right-0.5 h-2 w-2 rounded-full bg-green-500 ring-1 ring-gray-900" />
                                                    )}
                                                </div>
                                                <div>
                                                    <p className="font-medium text-white">{m.name}</p>
                                                    <p className="text-xs text-gray-500">{m.email}</p>
                                                </div>
                                            </div>
                                        </td>
                                        <td className="px-5 py-3">
                                            <span className="rounded-full bg-gray-700/50 px-2 py-0.5 text-xs text-gray-300">
                                                {ROLE_LABELS[m.role] ?? m.role}
                                            </span>
                                        </td>
                                        <td className="px-5 py-3">
                                            {m.is_active ? (
                                                <span className="text-xs text-green-400">Activo</span>
                                            ) : (
                                                <span className="text-xs text-gray-500">Inactivo</span>
                                            )}
                                        </td>
                                        <td className="px-5 py-3 text-right text-xs text-gray-500">
                                            {m.last_seen_at
                                                ? new Date(m.last_seen_at).toLocaleDateString('es')
                                                : '—'}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </SuperAdminLayout>
    );
}

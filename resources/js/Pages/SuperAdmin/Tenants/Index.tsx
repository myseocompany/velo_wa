import { Head, Link, router } from '@inertiajs/react';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout';
import { Search } from 'lucide-react';
import { useState } from 'react';

interface Tenant {
    id: string;
    name: string;
    slug: string;
    wa_status: string;
    wa_phone: string | null;
    max_agents: number | null;
    max_contacts: number | null;
    users_count: number;
    contacts_count: number;
    conversations_count: number;
    created_at: string;
}

interface PaginatedTenants {
    data: Tenant[];
    meta: { total: number; current_page: number; last_page: number };
    links: { prev: string | null; next: string | null };
}

const WA_STATUS_COLORS: Record<string, string> = {
    connected:    'bg-green-500/20 text-green-400',
    disconnected: 'bg-gray-500/20 text-gray-400',
    qr_pending:   'bg-yellow-500/20 text-yellow-400',
    banned:       'bg-red-500/20 text-red-400',
};

export default function TenantsIndex({
    tenants,
    filters,
}: {
    tenants: PaginatedTenants;
    filters: { search?: string; wa_status?: string };
}) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [waStatus, setWaStatus] = useState(filters.wa_status ?? '');

    const applyFilters = () => {
        router.get('/superadmin/tenants', { search, wa_status: waStatus }, { preserveScroll: true });
    };

    return (
        <SuperAdminLayout title="Tenants">
            <Head title="Tenants — Admin" />

            <div className="p-6 space-y-5">
                <div className="flex items-center justify-between">
                    <h1 className="text-xl font-bold text-white">
                        Tenants <span className="ml-2 text-base font-normal text-gray-500">({tenants.meta.total})</span>
                    </h1>
                </div>

                {/* Filters */}
                <div className="flex flex-wrap items-end gap-3">
                    <div className="relative">
                        <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-500" />
                        <input
                            type="text"
                            value={search}
                            onChange={e => setSearch(e.target.value)}
                            onKeyDown={e => e.key === 'Enter' && applyFilters()}
                            placeholder="Nombre, slug, teléfono..."
                            className="rounded-lg border border-gray-700 bg-gray-800 pl-9 pr-3 py-2 text-sm text-white placeholder-gray-500 focus:border-amber-500 focus:outline-none w-56"
                        />
                    </div>
                    <select
                        value={waStatus}
                        onChange={e => setWaStatus(e.target.value)}
                        className="rounded-lg border border-gray-700 bg-gray-800 px-3 py-2 text-sm text-gray-300 focus:border-amber-500 focus:outline-none"
                    >
                        <option value="">Todos los estados WA</option>
                        <option value="connected">Conectado</option>
                        <option value="disconnected">Desconectado</option>
                        <option value="qr_pending">QR pendiente</option>
                        <option value="banned">Baneado</option>
                    </select>
                    <button
                        onClick={applyFilters}
                        className="rounded-lg bg-amber-500 px-4 py-2 text-sm font-semibold text-gray-900 hover:bg-amber-400"
                    >
                        Filtrar
                    </button>
                </div>

                {/* Table */}
                <div className="rounded-xl border border-gray-800 bg-gray-900 overflow-hidden">
                    <table className="w-full text-sm">
                        <thead className="bg-gray-800/50 text-xs text-gray-500 uppercase">
                            <tr>
                                <th className="px-5 py-3 text-left">Tenant</th>
                                <th className="px-5 py-3 text-left">WhatsApp</th>
                                <th className="px-5 py-3 text-right">Agentes</th>
                                <th className="px-5 py-3 text-right">Contactos</th>
                                <th className="px-5 py-3 text-right">Convs.</th>
                                <th className="px-5 py-3 text-right">Creado</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-800">
                            {tenants.data.length === 0 && (
                                <tr>
                                    <td colSpan={6} className="px-5 py-10 text-center text-gray-500">
                                        No se encontraron tenants.
                                    </td>
                                </tr>
                            )}
                            {tenants.data.map(t => (
                                <tr key={t.id} className="hover:bg-gray-800/30">
                                    <td className="px-5 py-3">
                                        <Link href={`/superadmin/tenants/${t.id}`} className="font-medium text-white hover:text-amber-400">
                                            {t.name}
                                        </Link>
                                        <p className="text-xs text-gray-500">{t.slug}</p>
                                    </td>
                                    <td className="px-5 py-3">
                                        <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${WA_STATUS_COLORS[t.wa_status] ?? ''}`}>
                                            {t.wa_status}
                                        </span>
                                        {t.wa_phone && <p className="mt-0.5 text-xs text-gray-500">{t.wa_phone}</p>}
                                    </td>
                                    <td className="px-5 py-3 text-right text-gray-300">
                                        {t.users_count}{t.max_agents ? ` / ${t.max_agents}` : ''}
                                    </td>
                                    <td className="px-5 py-3 text-right text-gray-300">
                                        {t.contacts_count.toLocaleString()}{t.max_contacts ? ` / ${t.max_contacts.toLocaleString()}` : ''}
                                    </td>
                                    <td className="px-5 py-3 text-right text-gray-300">{t.conversations_count.toLocaleString()}</td>
                                    <td className="px-5 py-3 text-right text-xs text-gray-500">
                                        {new Date(t.created_at).toLocaleDateString('es')}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {/* Pagination */}
                {tenants.meta.last_page > 1 && (
                    <div className="flex flex-col gap-2 sm:flex-row sm:justify-end">
                        {tenants.links.prev && (
                            <Link href={tenants.links.prev} className="inline-flex min-h-11 items-center justify-center rounded border border-gray-700 px-3 py-1.5 text-xs text-gray-400 hover:bg-gray-800">
                                ← Anterior
                            </Link>
                        )}
                        <span className="inline-flex min-h-11 items-center justify-center px-3 py-1.5 text-xs text-gray-500">
                            Pág. {tenants.meta.current_page} / {tenants.meta.last_page}
                        </span>
                        {tenants.links.next && (
                            <Link href={tenants.links.next} className="inline-flex min-h-11 items-center justify-center rounded border border-gray-700 px-3 py-1.5 text-xs text-gray-400 hover:bg-gray-800">
                                Siguiente →
                            </Link>
                        )}
                    </div>
                )}
            </div>
        </SuperAdminLayout>
    );
}

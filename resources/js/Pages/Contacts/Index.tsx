import AppLayout from '@/Layouts/AppLayout';
import { Contact, PaginatedData } from '@/types';
import { Head } from '@inertiajs/react';
import axios from 'axios';
import { Search, Users } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

interface ContactsApiResponse extends PaginatedData<Contact> {}

export default function ContactsIndex() {
    const [search, setSearch] = useState('');
    const [loading, setLoading] = useState(true);
    const [contacts, setContacts] = useState<Contact[]>([]);
    const [total, setTotal] = useState(0);

    async function fetchContacts(query = '') {
        setLoading(true);
        try {
            const res = await axios.get<ContactsApiResponse>('/api/v1/contacts', {
                params: {
                    search: query || undefined,
                    per_page: 50,
                    sort: 'last_contact_at',
                    direction: 'desc',
                },
            });
            setContacts(res.data.data);
            setTotal(res.data.meta.total);
        } finally {
            setLoading(false);
        }
    }

    useEffect(() => {
        fetchContacts();
    }, []);

    useEffect(() => {
        const id = window.setTimeout(() => {
            fetchContacts(search.trim());
        }, 250);
        return () => window.clearTimeout(id);
    }, [search]);

    const emptyMessage = useMemo(() => {
        if (loading) return 'Cargando contactos...';
        if (search.trim()) return 'No hay contactos que coincidan con la búsqueda.';
        return 'Aún no hay contactos para este tenant.';
    }, [loading, search]);

    return (
        <AppLayout title="Contactos">
            <Head title="Contactos" />

            <div className="space-y-5 p-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold text-gray-900">Contactos</h1>
                        <p className="mt-1 text-sm text-gray-500">
                            {total} contacto{total !== 1 ? 's' : ''} registrado{total !== 1 ? 's' : ''}
                        </p>
                    </div>
                    <div className="relative w-full max-w-sm">
                        <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                        <input
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Buscar por nombre, teléfono o email"
                            className="w-full rounded-lg border border-gray-200 bg-white py-2 pl-10 pr-3 text-sm focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500"
                        />
                    </div>
                </div>

                <div className="overflow-hidden rounded-xl border border-gray-200 bg-white">
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-gray-200">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Contacto</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Teléfono</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Origen</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Último contacto</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Asignado</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {contacts.map((contact) => (
                                    <tr key={contact.id} className="hover:bg-gray-50">
                                        <td className="px-4 py-3">
                                            <p className="text-sm font-medium text-gray-900">
                                                {contact.name ?? contact.push_name ?? contact.phone ?? 'Sin nombre'}
                                            </p>
                                            {contact.email && (
                                                <p className="text-xs text-gray-500">{contact.email}</p>
                                            )}
                                        </td>
                                        <td className="px-4 py-3 text-sm text-gray-700">{contact.phone ?? '—'}</td>
                                        <td className="px-4 py-3 text-sm capitalize text-gray-700">{contact.source}</td>
                                        <td className="px-4 py-3 text-sm text-gray-700">
                                            {contact.last_contact_at
                                                ? new Date(contact.last_contact_at).toLocaleString('es-CO')
                                                : '—'}
                                        </td>
                                        <td className="px-4 py-3 text-sm text-gray-700">
                                            {contact.assignee?.name ?? 'Sin asignar'}
                                        </td>
                                    </tr>
                                ))}

                                {!contacts.length && (
                                    <tr>
                                        <td colSpan={5} className="px-4 py-10">
                                            <div className="flex flex-col items-center justify-center gap-2 text-gray-400">
                                                <Users className="h-8 w-8" />
                                                <p className="text-sm">{emptyMessage}</p>
                                            </div>
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}

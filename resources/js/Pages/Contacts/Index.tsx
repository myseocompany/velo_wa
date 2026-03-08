import AppLayout from '@/Layouts/AppLayout';
import { Contact, PaginatedData, User } from '@/types';
import { router } from '@inertiajs/react';
import axios from 'axios';
import { formatDistanceToNow } from 'date-fns';
import { es } from 'date-fns/locale';
import {
    ArrowDown,
    ArrowUp,
    ArrowUpDown,
    ChevronLeft,
    ChevronRight,
    Search,
    Tag,
    UserCheck,
    Users,
    X,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

type SortField = 'name' | 'created_at' | 'last_contact_at';
type SortDir   = 'asc' | 'desc';

const SOURCE_LABELS: Record<string, string> = {
    whatsapp: 'WhatsApp',
    manual:   'Manual',
    import:   'Importado',
};

function SortIcon({ field, current, dir }: { field: SortField; current: SortField; dir: SortDir }) {
    if (field !== current) return <ArrowUpDown className="ml-1 inline h-3.5 w-3.5 opacity-40" />;
    return dir === 'asc'
        ? <ArrowUp className="ml-1 inline h-3.5 w-3.5 text-brand-600" />
        : <ArrowDown className="ml-1 inline h-3.5 w-3.5 text-brand-600" />;
}

export default function ContactsIndex() {
    const [search, setSearch]                     = useState('');
    const [loading, setLoading]                   = useState(true);
    const [contacts, setContacts]                 = useState<Contact[]>([]);
    const [total, setTotal]                       = useState(0);
    const [page, setPage]                         = useState(1);
    const [lastPage, setLastPage]                 = useState(1);
    const [sort, setSort]                         = useState<SortField>('last_contact_at');
    const [dir, setDir]                           = useState<SortDir>('desc');
    const [agents, setAgents]                     = useState<User[]>([]);
    const [availableTags, setAvailableTags]       = useState<string[]>([]);
    const [selectedTags, setSelectedTags]         = useState<string[]>([]);
    const [assignedFilter, setAssignedFilter]     = useState('');
    const [showTagPicker, setShowTagPicker]       = useState(false);
    const tagPickerRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        axios.get<{ data: User[] }>('/api/v1/team/members').then((r) => setAgents(r.data.data));
        axios.get<{ data: string[] }>('/api/v1/contacts/tags').then((r) => setAvailableTags(r.data.data));
    }, []);

    useEffect(() => {
        function handleClick(e: MouseEvent) {
            if (tagPickerRef.current && !tagPickerRef.current.contains(e.target as Node)) {
                setShowTagPicker(false);
            }
        }
        document.addEventListener('mousedown', handleClick);
        return () => document.removeEventListener('mousedown', handleClick);
    }, []);

    useEffect(() => {
        let cancelled = false;
        const delay = search === '' ? 0 : 250;

        const id = window.setTimeout(async () => {
            setLoading(true);
            try {
                const params: Record<string, unknown> = {
                    per_page: 25,
                    page,
                    sort,
                    direction: dir,
                };
                if (search.trim())       params.search      = search.trim();
                if (selectedTags.length) params['tags[]']   = selectedTags;
                if (assignedFilter)      params.assigned    = assignedFilter;

                const res = await axios.get<PaginatedData<Contact>>('/api/v1/contacts', { params });
                if (!cancelled) {
                    setContacts(res.data.data);
                    setTotal(res.data.meta.total);
                    setLastPage(res.data.meta.last_page);
                }
            } finally {
                if (!cancelled) setLoading(false);
            }
        }, delay);

        return () => { cancelled = true; window.clearTimeout(id); };
    }, [search, page, sort, dir, selectedTags, assignedFilter]);

    function toggleSort(field: SortField) {
        if (sort === field) {
            setDir((d) => (d === 'asc' ? 'desc' : 'asc'));
        } else {
            setSort(field);
            setDir('asc');
        }
        setPage(1);
    }

    function toggleTag(tag: string) {
        setSelectedTags((prev) =>
            prev.includes(tag) ? prev.filter((t) => t !== tag) : [...prev, tag],
        );
        setPage(1);
    }

    return (
        <AppLayout title="Contactos">
            <div className="space-y-5 p-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold text-gray-900">Contactos</h1>
                        <p className="mt-1 text-sm text-gray-500">{total} contacto{total !== 1 ? 's' : ''} en total</p>
                    </div>
                </div>

                {/* Filters */}
                <div className="flex flex-wrap items-center gap-3">
                    <div className="relative min-w-[220px] flex-1 max-w-sm">
                        <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                        <input
                            value={search}
                            onChange={(e) => { setSearch(e.target.value); setPage(1); }}
                            placeholder="Buscar por nombre, teléfono o email"
                            className="w-full rounded-lg border border-gray-200 bg-white py-2 pl-10 pr-3 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500"
                        />
                    </div>

                    <div ref={tagPickerRef} className="relative">
                        <button
                            onClick={() => setShowTagPicker(!showTagPicker)}
                            className={`flex items-center gap-1.5 rounded-lg border px-3 py-2 text-sm font-medium ${
                                selectedTags.length
                                    ? 'border-brand-500 bg-brand-50 text-brand-700'
                                    : 'border-gray-200 bg-white text-gray-700 hover:bg-gray-50'
                            }`}
                        >
                            <Tag className="h-3.5 w-3.5" />
                            {selectedTags.length ? `${selectedTags.length} etiqueta${selectedTags.length > 1 ? 's' : ''}` : 'Etiquetas'}
                        </button>
                        {showTagPicker && (
                            <div className="absolute left-0 top-full z-10 mt-1 w-52 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-lg">
                                {availableTags.length === 0 ? (
                                    <p className="px-3 py-3 text-xs text-gray-400">Sin etiquetas disponibles</p>
                                ) : availableTags.map((tag) => (
                                    <button
                                        key={tag}
                                        onClick={() => toggleTag(tag)}
                                        className={`flex w-full items-center gap-2 px-3 py-2 text-sm hover:bg-gray-50 ${
                                            selectedTags.includes(tag) ? 'font-semibold text-brand-600' : 'text-gray-800'
                                        }`}
                                    >
                                        <span className={`h-2 w-2 flex-shrink-0 rounded-full ${selectedTags.includes(tag) ? 'bg-brand-600' : 'bg-gray-300'}`} />
                                        {tag}
                                    </button>
                                ))}
                            </div>
                        )}
                    </div>

                    <select
                        value={assignedFilter}
                        onChange={(e) => { setAssignedFilter(e.target.value); setPage(1); }}
                        className="rounded-lg border border-gray-200 bg-white py-2 pl-3 pr-8 text-sm text-gray-700 focus:border-brand-500 focus:outline-none"
                    >
                        <option value="">Todos los agentes</option>
                        <option value="me">Asignados a mí</option>
                        <option value="unassigned">Sin asignar</option>
                        {agents.map((a) => <option key={a.id} value={a.id}>{a.name}</option>)}
                    </select>

                    {selectedTags.map((tag) => (
                        <span key={tag} className="flex items-center gap-1 rounded-full bg-brand-100 px-2.5 py-1 text-xs font-medium text-brand-700">
                            {tag}
                            <button onClick={() => toggleTag(tag)}><X className="h-3 w-3" /></button>
                        </span>
                    ))}
                </div>

                {/* Table */}
                <div className="overflow-hidden rounded-xl border border-gray-200 bg-white">
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-gray-200">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                        <button onClick={() => toggleSort('name')} className="flex items-center hover:text-gray-700">
                                            Contacto <SortIcon field="name" current={sort} dir={dir} />
                                        </button>
                                    </th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Etiquetas</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Origen</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                        <button onClick={() => toggleSort('last_contact_at')} className="flex items-center hover:text-gray-700">
                                            Último contacto <SortIcon field="last_contact_at" current={sort} dir={dir} />
                                        </button>
                                    </th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Asignado</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {loading ? (
                                    <tr><td colSpan={5} className="py-12 text-center">
                                        <div className="mx-auto h-6 w-6 animate-spin rounded-full border-2 border-brand-600 border-t-transparent" />
                                    </td></tr>
                                ) : contacts.length === 0 ? (
                                    <tr><td colSpan={5} className="px-4 py-10">
                                        <div className="flex flex-col items-center justify-center gap-2 text-gray-400">
                                            <Users className="h-8 w-8" />
                                            <p className="text-sm">{search || selectedTags.length || assignedFilter ? 'Sin resultados para los filtros aplicados.' : 'Aún no hay contactos.'}</p>
                                        </div>
                                    </td></tr>
                                ) : contacts.map((contact) => (
                                    <tr key={contact.id} className="cursor-pointer hover:bg-gray-50" onClick={() => router.visit(`/contacts/${contact.id}`)}>
                                        <td className="px-4 py-3">
                                            <p className="text-sm font-medium text-gray-900">
                                                {contact.name ?? contact.push_name ?? contact.phone ?? 'Sin nombre'}
                                            </p>
                                            {contact.company && <p className="text-xs text-gray-400">{contact.company}</p>}
                                            {contact.phone && <p className="text-xs text-gray-400">{contact.phone}</p>}
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="flex flex-wrap gap-1">
                                                {(contact.tags ?? []).slice(0, 3).map((tag) => (
                                                    <span key={tag} className="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600">{tag}</span>
                                                ))}
                                                {(contact.tags ?? []).length > 3 && (
                                                    <span className="text-xs text-gray-400">+{(contact.tags ?? []).length - 3}</span>
                                                )}
                                            </div>
                                        </td>
                                        <td className="px-4 py-3 text-sm text-gray-700">{SOURCE_LABELS[contact.source ?? ''] ?? contact.source}</td>
                                        <td className="px-4 py-3 text-sm text-gray-500">
                                            {contact.last_contact_at
                                                ? formatDistanceToNow(new Date(contact.last_contact_at), { addSuffix: true, locale: es })
                                                : '—'}
                                        </td>
                                        <td className="px-4 py-3">
                                            {contact.assignee ? (
                                                <span className="flex items-center gap-1.5 text-sm text-gray-700">
                                                    <UserCheck className="h-3.5 w-3.5 text-brand-500" />{contact.assignee.name}
                                                </span>
                                            ) : (
                                                <span className="text-sm text-gray-400">Sin asignar</span>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    {lastPage > 1 && (
                        <div className="flex items-center justify-between border-t border-gray-100 px-4 py-3">
                            <p className="text-xs text-gray-500">Página {page} de {lastPage} · {total} contactos</p>
                            <div className="flex gap-1">
                                <button onClick={() => setPage((p) => Math.max(1, p - 1))} disabled={page === 1} className="rounded-lg p-1.5 text-gray-500 hover:bg-gray-100 disabled:opacity-30">
                                    <ChevronLeft className="h-4 w-4" />
                                </button>
                                <button onClick={() => setPage((p) => Math.min(lastPage, p + 1))} disabled={page === lastPage} className="rounded-lg p-1.5 text-gray-500 hover:bg-gray-100 disabled:opacity-30">
                                    <ChevronRight className="h-4 w-4" />
                                </button>
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}

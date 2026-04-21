import AppLayout from '@/Layouts/AppLayout';
import { Contact, PaginatedData, Tag as TagType, User } from '@/types';
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
    GitMerge,
    Plus,
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
        ? <ArrowUp className="ml-1 inline h-3.5 w-3.5 text-ari-600" />
        : <ArrowDown className="ml-1 inline h-3.5 w-3.5 text-ari-600" />;
}

// ─── Create contact modal ─────────────────────────────────────────────────────

interface CreateModalProps {
    agents: User[];
    onClose: () => void;
    onCreated: (contact: Contact) => void;
}

function CreateContactModal({ agents, onClose, onCreated }: CreateModalProps) {
    const [phone, setPhone]     = useState('');
    const [name, setName]       = useState('');
    const [email, setEmail]     = useState('');
    const [company, setCompany] = useState('');
    const [assignedTo, setAssignedTo] = useState('');
    const [saving, setSaving]   = useState(false);
    const [errors, setErrors]   = useState<Record<string, string>>({});

    async function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        setSaving(true);
        setErrors({});
        try {
            const res = await axios.post<{ data: Contact }>('/api/v1/contacts', {
                phone:       phone.trim(),
                name:        name.trim() || null,
                email:       email.trim() || null,
                company:     company.trim() || null,
                assigned_to: assignedTo || null,
            });
            onCreated(res.data.data);
        } catch (err: unknown) {
            if (axios.isAxiosError(err) && err.response?.data?.errors) {
                const errs: Record<string, string> = {};
                for (const [k, v] of Object.entries(err.response.data.errors)) {
                    errs[k] = Array.isArray(v) ? (v as string[])[0] : String(v);
                }
                setErrors(errs);
            }
        } finally {
            setSaving(false);
        }
    }

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div className="w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-xl">
                <div className="flex items-center justify-between border-b border-gray-100 px-5 py-4">
                    <h3 className="font-semibold text-gray-900">Nuevo contacto</h3>
                    <button onClick={onClose} className="text-gray-400 hover:text-gray-600">
                        <X className="h-5 w-5" />
                    </button>
                </div>

                <form onSubmit={handleSubmit} className="space-y-4 px-5 py-4">
                    <div>
                        <label className="mb-1 block text-xs font-medium text-gray-700">
                            Teléfono <span className="text-red-500">*</span>
                        </label>
                        <input
                            value={phone}
                            onChange={(e) => setPhone(e.target.value)}
                            placeholder="+57 300 0000000"
                            className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-ari-500 focus:outline-none"
                        />
                        {errors.phone && <p className="mt-0.5 text-xs text-red-500">{errors.phone}</p>}
                    </div>
                    <div>
                        <label className="mb-1 block text-xs font-medium text-gray-700">Nombre</label>
                        <input
                            value={name}
                            onChange={(e) => setName(e.target.value)}
                            className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-ari-500 focus:outline-none"
                        />
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                        <div>
                            <label className="mb-1 block text-xs font-medium text-gray-700">Email</label>
                            <input
                                value={email}
                                onChange={(e) => setEmail(e.target.value)}
                                type="email"
                                className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-ari-500 focus:outline-none"
                            />
                        </div>
                        <div>
                            <label className="mb-1 block text-xs font-medium text-gray-700">Empresa</label>
                            <input
                                value={company}
                                onChange={(e) => setCompany(e.target.value)}
                                className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-ari-500 focus:outline-none"
                            />
                        </div>
                    </div>
                    <div>
                        <label className="mb-1 block text-xs font-medium text-gray-700">Asignar a</label>
                        <select
                            value={assignedTo}
                            onChange={(e) => setAssignedTo(e.target.value)}
                            className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-ari-500 focus:outline-none"
                        >
                            <option value="">Sin asignar</option>
                            {agents.map((a) => <option key={a.id} value={a.id}>{a.name}</option>)}
                        </select>
                    </div>

                    <div className="flex justify-end gap-2 pt-1">
                        <button type="button" onClick={onClose}
                            className="rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                            Cancelar
                        </button>
                        <button type="submit" disabled={saving || !phone.trim()}
                            className="flex items-center gap-1.5 rounded-lg bg-ari-600 px-4 py-2 text-sm font-medium text-white hover:bg-ari-700 disabled:opacity-50">
                            {saving
                                ? <div className="h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent" />
                                : <Plus className="h-4 w-4" />}
                            Crear contacto
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}

// ─── Merge modal ──────────────────────────────────────────────────────────────

interface MergeModalProps {
    source: Contact;
    onClose: () => void;
    onMerged: () => void;
}

function MergeModal({ source, onClose, onMerged }: MergeModalProps) {
    const [phone, setPhone]         = useState('');
    const [candidates, setCandidates] = useState<Contact[]>([]);
    const [searching, setSearching] = useState(false);
    const [target, setTarget]       = useState<Contact | null>(null);
    const [merging, setMerging]     = useState(false);
    const sourceDisplayName = source.name ?? source.push_name ?? source.phone ?? 'Sin nombre';

    async function search() {
        if (!phone.trim()) return;
        setSearching(true);
        const res = await axios.get<PaginatedData<Contact>>('/api/v1/contacts', {
            params: { search: phone.trim(), per_page: 10 },
        });
        setCandidates(res.data.data.filter((c) => c.id !== source.id));
        setSearching(false);
    }

    async function doMerge() {
        if (!target) return;
        setMerging(true);
        try {
            await axios.post(`/api/v1/contacts/${source.id}/merge`, { merge_into_id: target.id });
            onMerged();
        } finally {
            setMerging(false);
        }
    }

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div className="w-full max-w-lg overflow-hidden rounded-2xl bg-white shadow-xl">
                <div className="flex items-center justify-between border-b border-gray-100 px-5 py-4">
                    <h3 className="font-semibold text-gray-900">Combinar contacto</h3>
                    <button onClick={onClose} className="text-gray-400 hover:text-gray-600">
                        <X className="h-5 w-5" />
                    </button>
                </div>

                <div className="px-5 py-4 space-y-4">
                    <p className="text-sm text-gray-600">
                        Fusionar <span className="font-medium text-gray-900">{sourceDisplayName}</span> en otro contacto.
                        Las conversaciones y deals se transferirán al destino, y el origen se eliminará.
                    </p>

                    {/* Search */}
                    <div className="flex gap-2">
                        <input
                            value={phone}
                            onChange={(e) => setPhone(e.target.value)}
                            onKeyDown={(e) => e.key === 'Enter' && search()}
                            placeholder="Buscar por nombre o teléfono…"
                            className="flex-1 rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-ari-500 focus:outline-none"
                        />
                        <button onClick={search} disabled={searching}
                            className="rounded-lg bg-gray-100 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200 disabled:opacity-50">
                            {searching ? <div className="h-4 w-4 animate-spin rounded-full border-2 border-gray-400 border-t-transparent" /> : <Search className="h-4 w-4" />}
                        </button>
                    </div>

                    {/* Candidates */}
                    {candidates.length > 0 && (
                        <ul className="max-h-48 divide-y divide-gray-100 overflow-y-auto rounded-xl border border-gray-200">
                            {candidates.map((c) => (
                                <li key={c.id}>
                                    <button
                                        onClick={() => setTarget(c)}
                                        className={`flex w-full items-start gap-3 px-3 py-2.5 text-left hover:bg-gray-50 ${target?.id === c.id ? 'bg-ari-50' : ''}`}
                                    >
                                        <div className="flex h-8 w-8 flex-shrari-0 items-center justify-center rounded-full bg-ari-100 text-xs font-bold text-ari-700">
                                            {(c.name ?? c.push_name ?? c.phone ?? '?').charAt(0).toUpperCase()}
                                        </div>
                                        <div className="min-w-0">
                                            <p className="text-sm font-medium text-gray-900 truncate">{c.name ?? c.push_name ?? 'Sin nombre'}</p>
                                            <p className="text-xs text-gray-400">{c.phone}</p>
                                        </div>
                                        {target?.id === c.id && <span className="ml-auto text-xs font-medium text-ari-600">Seleccionado</span>}
                                    </button>
                                </li>
                            ))}
                        </ul>
                    )}
                    {candidates.length === 0 && phone && !searching && (
                        <p className="text-center text-sm text-gray-400">Sin resultados.</p>
                    )}
                </div>

                <div className="flex justify-end gap-2 border-t border-gray-100 px-5 py-3">
                    <button onClick={onClose}
                        className="rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                        Cancelar
                    </button>
                    <button onClick={doMerge} disabled={!target || merging}
                        className="flex items-center gap-1.5 rounded-lg bg-orange-600 px-4 py-2 text-sm font-medium text-white hover:bg-orange-700 disabled:opacity-50">
                        {merging
                            ? <div className="h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent" />
                            : <GitMerge className="h-4 w-4" />}
                        Combinar
                    </button>
                </div>
            </div>
        </div>
    );
}

// ─── Main page ────────────────────────────────────────────────────────────────

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
    const [availableTags, setAvailableTags]       = useState<TagType[]>([]);
    const [selectedTags, setSelectedTags]         = useState<string[]>([]); // stores tag IDs
    const [assignedFilter, setAssignedFilter]     = useState('');
    const [showTagPicker, setShowTagPicker]       = useState(false);
    const [showCreateModal, setShowCreateModal]   = useState(false);
    const [mergeContact, setMergeContact]         = useState<Contact | null>(null);
    const tagPickerRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        axios.get<{ data: User[] }>('/api/v1/team/members').then((r) => setAgents(r.data.data));
        axios.get<{ data: TagType[] }>('/api/v1/tags').then((r) => setAvailableTags(r.data.data));
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
                if (selectedTags.length) params['tag_ids[]'] = selectedTags;
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

    function handleCreated(contact: Contact) {
        setShowCreateModal(false);
        setTotal((t) => t + 1);
        setContacts((prev) => [contact, ...prev]);
    }

    function handleMerged() {
        setMergeContact(null);
        // Reload contacts list
        setPage(1);
        setSearch('');
        setSelectedTags([]);
    }

    return (
        <AppLayout title="Contactos">
            <div className="space-y-5 p-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold text-gray-900">Contactos</h1>
                        <p className="mt-1 text-sm text-gray-500">{total} contacto{total !== 1 ? 's' : ''} en total</p>
                    </div>
                    <button
                        onClick={() => setShowCreateModal(true)}
                        className="flex items-center gap-2 rounded-lg bg-ari-600 px-4 py-2 text-sm font-medium text-white hover:bg-ari-700"
                    >
                        <Plus className="h-4 w-4" />
                        Nuevo contacto
                    </button>
                </div>

                {/* Filters */}
                <div className="flex flex-wrap items-center gap-3">
                    <div className="relative min-w-[220px] flex-1 max-w-sm">
                        <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                        <input
                            value={search}
                            onChange={(e) => { setSearch(e.target.value); setPage(1); }}
                            placeholder="Buscar por nombre, teléfono o email"
                            className="w-full rounded-lg border border-gray-200 bg-white py-2 pl-10 pr-3 text-sm focus:border-ari-500 focus:outline-none focus:ring-1 focus:ring-ari-500"
                        />
                    </div>

                    <div ref={tagPickerRef} className="relative">
                        <button
                            onClick={() => setShowTagPicker(!showTagPicker)}
                            className={`flex items-center gap-1.5 rounded-lg border px-3 py-2 text-sm font-medium ${
                                selectedTags.length
                                    ? 'border-ari-500 bg-ari-50 text-ari-700'
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
                                        key={tag.id}
                                        onClick={() => toggleTag(tag.id)}
                                        className={`flex w-full items-center gap-2 px-3 py-2 text-sm hover:bg-gray-50 ${
                                            selectedTags.includes(tag.id) ? 'font-semibold text-ari-600' : 'text-gray-800'
                                        }`}
                                    >
                                        <span
                                            className="h-2 w-2 flex-shrink-0 rounded-full"
                                            style={{ backgroundColor: selectedTags.includes(tag.id) ? tag.color : '#d1d5db' }}
                                        />
                                        {tag.name}
                                    </button>
                                ))}
                            </div>
                        )}
                    </div>

                    <select
                        value={assignedFilter}
                        onChange={(e) => { setAssignedFilter(e.target.value); setPage(1); }}
                        className="rounded-lg border border-gray-200 bg-white py-2 pl-3 pr-8 text-sm text-gray-700 focus:border-ari-500 focus:outline-none"
                    >
                        <option value="">Todos los agentes</option>
                        <option value="me">Asignados a mí</option>
                        <option value="unassigned">Sin asignar</option>
                        {agents.map((a) => <option key={a.id} value={a.id}>{a.name}</option>)}
                    </select>

                    {selectedTags.map((tagId) => {
                        const tag = availableTags.find((t) => t.id === tagId);
                        return tag ? (
                            <span
                                key={tagId}
                                className="flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-medium text-white"
                                style={{ backgroundColor: tag.color }}
                            >
                                {tag.name}
                                <button onClick={() => toggleTag(tagId)}><X className="h-3 w-3" /></button>
                            </span>
                        ) : null;
                    })}
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
                                    <th className="px-4 py-3" />
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {loading ? (
                                    <tr><td colSpan={6} className="py-12 text-center">
                                        <div className="mx-auto h-6 w-6 animate-spin rounded-full border-2 border-ari-600 border-t-transparent" />
                                    </td></tr>
                                ) : contacts.length === 0 ? (
                                    <tr><td colSpan={6} className="px-4 py-10">
                                        <div className="flex flex-col items-center justify-center gap-2 text-gray-400">
                                            <Users className="h-8 w-8" />
                                            <p className="text-sm">{search || selectedTags.length || assignedFilter ? 'Sin resultados para los filtros aplicados.' : 'Aún no hay contactos.'}</p>
                                        </div>
                                    </td></tr>
                                ) : contacts.map((contact) => (
                                    <tr key={contact.id} className="group cursor-pointer hover:bg-gray-50" onClick={() => router.visit(`/contacts/${contact.id}`)}>
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
                                                    <span
                                                        key={tag.id}
                                                        className="rounded-full px-2 py-0.5 text-xs font-medium text-white"
                                                        style={{ backgroundColor: tag.color }}
                                                    >
                                                        {tag.name}
                                                    </span>
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
                                                    <UserCheck className="h-3.5 w-3.5 text-ari-500" />{contact.assignee.name}
                                                </span>
                                            ) : (
                                                <span className="text-sm text-gray-400">Sin asignar</span>
                                            )}
                                        </td>
                                        <td className="px-4 py-3 text-right">
                                            <button
                                                onClick={(e) => { e.stopPropagation(); setMergeContact(contact); }}
                                                className="invisible rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 group-hover:visible"
                                                title="Combinar contacto"
                                            >
                                                <GitMerge className="h-4 w-4" />
                                            </button>
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

            {showCreateModal && (
                <CreateContactModal agents={agents} onClose={() => setShowCreateModal(false)} onCreated={handleCreated} />
            )}
            {mergeContact && (
                <MergeModal source={mergeContact} onClose={() => setMergeContact(null)} onMerged={handleMerged} />
            )}
        </AppLayout>
    );
}

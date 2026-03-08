import AppLayout from '@/Layouts/AppLayout';
import { Contact, User } from '@/types';
import { router } from '@inertiajs/react';
import axios from 'axios';
import { formatDistanceToNow } from 'date-fns';
import { es } from 'date-fns/locale';
import {
    ArrowLeft,
    Building2,
    Check,
    Mail,
    MessageSquare,
    Pencil,
    Phone,
    Tag,
    UserCheck,
    X,
} from 'lucide-react';
import { useEffect, useState } from 'react';

interface Props {
    contactId: string;
}

interface ConversationSummary {
    id: string;
    status: string;
    message_count: number;
    last_message_at: string | null;
    created_at: string;
}

const STATUS_LABELS: Record<string, string> = { open: 'Abierta', pending: 'Pendiente', closed: 'Cerrada' };
const STATUS_COLORS: Record<string, string> = {
    open:    'bg-green-100 text-green-700',
    pending: 'bg-yellow-100 text-yellow-700',
    closed:  'bg-gray-100 text-gray-600',
};

function TagInput({ tags, onChange }: { tags: string[]; onChange: (tags: string[]) => void }) {
    const [input, setInput] = useState('');

    function addTag() {
        const trimmed = input.trim().toLowerCase();
        if (trimmed && !tags.includes(trimmed)) {
            onChange([...tags, trimmed]);
        }
        setInput('');
    }

    function removeTag(tag: string) {
        onChange(tags.filter((t) => t !== tag));
    }

    return (
        <div className="flex flex-wrap items-center gap-1.5 rounded-lg border border-gray-200 px-3 py-2">
            {tags.map((tag) => (
                <span key={tag} className="flex items-center gap-1 rounded-full bg-brand-100 px-2 py-0.5 text-xs font-medium text-brand-700">
                    {tag}
                    <button onClick={() => removeTag(tag)}><X className="h-3 w-3" /></button>
                </span>
            ))}
            <input
                value={input}
                onChange={(e) => setInput(e.target.value)}
                onKeyDown={(e) => {
                    if (e.key === 'Enter' || e.key === ',') { e.preventDefault(); addTag(); }
                    if (e.key === 'Backspace' && !input && tags.length) onChange(tags.slice(0, -1));
                }}
                placeholder="Añadir etiqueta…"
                className="min-w-[100px] flex-1 border-none bg-transparent text-sm outline-none placeholder-gray-400"
            />
        </div>
    );
}

export default function ContactShow({ contactId }: Props) {
    const [contact, setContact]   = useState<Contact | null>(null);
    const [loading, setLoading]   = useState(true);
    const [editing, setEditing]   = useState(false);
    const [saving, setSaving]     = useState(false);
    const [agents, setAgents]     = useState<User[]>([]);

    // Edit state
    const [editName, setEditName]         = useState('');
    const [editEmail, setEditEmail]       = useState('');
    const [editCompany, setEditCompany]   = useState('');
    const [editNotes, setEditNotes]       = useState('');
    const [editTags, setEditTags]         = useState<string[]>([]);
    const [editAssigned, setEditAssigned] = useState('');

    useEffect(() => {
        axios.get<{ data: User[] }>('/api/v1/team/members').then((r) => setAgents(r.data.data));
    }, []);

    useEffect(() => {
        setLoading(true);
        axios.get<{ data: Contact }>(`/api/v1/contacts/${contactId}`)
            .then((r) => setContact(r.data.data))
            .finally(() => setLoading(false));
    }, [contactId]);

    function startEdit() {
        if (!contact) return;
        setEditName(contact.name ?? '');
        setEditEmail(contact.email ?? '');
        setEditCompany(contact.company ?? '');
        setEditNotes(contact.notes ?? '');
        setEditTags(contact.tags ?? []);
        setEditAssigned(contact.assigned_to ?? '');
        setEditing(true);
    }

    async function saveEdit() {
        if (!contact) return;
        setSaving(true);
        try {
            const res = await axios.patch<{ data: Contact }>(`/api/v1/contacts/${contact.id}`, {
                name:        editName || null,
                email:       editEmail || null,
                company:     editCompany || null,
                notes:       editNotes || null,
                tags:        editTags,
                assigned_to: editAssigned || null,
            });
            setContact(res.data.data);
            setEditing(false);
        } finally {
            setSaving(false);
        }
    }

    if (loading) {
        return (
            <AppLayout title="Contacto">
                <div className="flex h-64 items-center justify-center">
                    <div className="h-6 w-6 animate-spin rounded-full border-2 border-brand-600 border-t-transparent" />
                </div>
            </AppLayout>
        );
    }

    if (!contact) {
        return (
            <AppLayout title="Contacto">
                <div className="p-6 text-center text-gray-500">Contacto no encontrado.</div>
            </AppLayout>
        );
    }

    const displayName = contact.name ?? contact.push_name ?? contact.phone ?? 'Sin nombre';
    const conversations = (contact as any).conversations as ConversationSummary[] | undefined ?? [];

    return (
        <AppLayout title={displayName}>
            <div className="p-6">
                {/* Back */}
                <button onClick={() => router.visit('/contacts')} className="mb-4 flex items-center gap-1.5 text-sm text-gray-500 hover:text-gray-700">
                    <ArrowLeft className="h-4 w-4" /> Contactos
                </button>

                <div className="flex flex-col gap-6 lg:flex-row">
                    {/* Left — contact card */}
                    <div className="w-full lg:w-80 flex-shrink-0">
                        <div className="overflow-hidden rounded-xl border border-gray-200 bg-white">
                            <div className="bg-gradient-to-br from-brand-50 to-brand-100 px-5 py-6 text-center">
                                <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-brand-600 text-2xl font-bold text-white">
                                    {displayName.charAt(0).toUpperCase()}
                                </div>
                                <h2 className="mt-3 text-lg font-semibold text-gray-900">{displayName}</h2>
                                {contact.push_name && contact.name && contact.push_name !== contact.name && (
                                    <p className="text-xs text-gray-500">{contact.push_name}</p>
                                )}
                            </div>

                            <div className="divide-y divide-gray-100 px-4 py-2">
                                {contact.phone && (
                                    <div className="flex items-center gap-3 py-2.5">
                                        <Phone className="h-4 w-4 text-gray-400" />
                                        <span className="text-sm text-gray-700">{contact.phone}</span>
                                    </div>
                                )}
                                {contact.email && (
                                    <div className="flex items-center gap-3 py-2.5">
                                        <Mail className="h-4 w-4 text-gray-400" />
                                        <span className="text-sm text-gray-700">{contact.email}</span>
                                    </div>
                                )}
                                {contact.company && (
                                    <div className="flex items-center gap-3 py-2.5">
                                        <Building2 className="h-4 w-4 text-gray-400" />
                                        <span className="text-sm text-gray-700">{contact.company}</span>
                                    </div>
                                )}
                                {contact.assignee && (
                                    <div className="flex items-center gap-3 py-2.5">
                                        <UserCheck className="h-4 w-4 text-brand-500" />
                                        <span className="text-sm text-gray-700">{contact.assignee.name}</span>
                                    </div>
                                )}
                                {(contact.tags ?? []).length > 0 && (
                                    <div className="flex items-start gap-3 py-2.5">
                                        <Tag className="mt-0.5 h-4 w-4 text-gray-400" />
                                        <div className="flex flex-wrap gap-1">
                                            {(contact.tags ?? []).map((tag) => (
                                                <span key={tag} className="rounded-full bg-brand-50 px-2 py-0.5 text-xs font-medium text-brand-700">{tag}</span>
                                            ))}
                                        </div>
                                    </div>
                                )}
                            </div>

                            <div className="border-t border-gray-100 px-4 py-3">
                                <button
                                    onClick={startEdit}
                                    className="flex w-full items-center justify-center gap-1.5 rounded-lg border border-gray-200 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                                >
                                    <Pencil className="h-3.5 w-3.5" /> Editar contacto
                                </button>
                            </div>
                        </div>

                        {/* Dates */}
                        <div className="mt-3 rounded-xl border border-gray-200 bg-white px-4 py-3 text-xs text-gray-500 space-y-1">
                            {contact.first_contact_at && (
                                <p>Primer contacto: {formatDistanceToNow(new Date(contact.first_contact_at), { addSuffix: true, locale: es })}</p>
                            )}
                            {contact.last_contact_at && (
                                <p>Último contacto: {formatDistanceToNow(new Date(contact.last_contact_at), { addSuffix: true, locale: es })}</p>
                            )}
                        </div>
                    </div>

                    {/* Right — conversations + notes */}
                    <div className="flex-1 space-y-5">
                        {/* Notes */}
                        {contact.notes && (
                            <div className="rounded-xl border border-yellow-200 bg-yellow-50 px-4 py-3">
                                <p className="mb-1 text-xs font-semibold uppercase tracking-wider text-yellow-700">Notas</p>
                                <p className="text-sm text-gray-700 whitespace-pre-wrap">{contact.notes}</p>
                            </div>
                        )}

                        {/* Conversations */}
                        <div className="overflow-hidden rounded-xl border border-gray-200 bg-white">
                            <div className="flex items-center justify-between border-b border-gray-100 px-4 py-3">
                                <h3 className="flex items-center gap-2 text-sm font-semibold text-gray-800">
                                    <MessageSquare className="h-4 w-4 text-brand-500" />
                                    Conversaciones ({conversations.length})
                                </h3>
                            </div>
                            {conversations.length === 0 ? (
                                <p className="px-4 py-6 text-center text-sm text-gray-400">Sin conversaciones aún.</p>
                            ) : (
                                <ul className="divide-y divide-gray-100">
                                    {conversations.map((conv) => (
                                        <li key={conv.id}>
                                            <button
                                                onClick={() => router.visit(`/inbox/${conv.id}`)}
                                                className="flex w-full items-center justify-between px-4 py-3 text-left hover:bg-gray-50"
                                            >
                                                <div className="flex items-center gap-3">
                                                    <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${STATUS_COLORS[conv.status] ?? 'bg-gray-100 text-gray-600'}`}>
                                                        {STATUS_LABELS[conv.status] ?? conv.status}
                                                    </span>
                                                    <span className="text-sm text-gray-700">{conv.message_count} mensajes</span>
                                                </div>
                                                <span className="text-xs text-gray-400">
                                                    {conv.last_message_at
                                                        ? formatDistanceToNow(new Date(conv.last_message_at), { addSuffix: true, locale: es })
                                                        : formatDistanceToNow(new Date(conv.created_at), { addSuffix: true, locale: es })}
                                                </span>
                                            </button>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </div>
                    </div>
                </div>
            </div>

            {/* Edit modal */}
            {editing && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
                    <div className="w-full max-w-lg overflow-hidden rounded-2xl bg-white shadow-xl">
                        <div className="flex items-center justify-between border-b border-gray-100 px-5 py-4">
                            <h3 className="font-semibold text-gray-900">Editar contacto</h3>
                            <button onClick={() => setEditing(false)} className="text-gray-400 hover:text-gray-600">
                                <X className="h-5 w-5" />
                            </button>
                        </div>

                        <div className="space-y-4 px-5 py-4">
                            <div>
                                <label className="mb-1 block text-xs font-medium text-gray-700">Nombre</label>
                                <input value={editName} onChange={(e) => setEditName(e.target.value)}
                                    className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none" />
                            </div>
                            <div className="grid grid-cols-2 gap-3">
                                <div>
                                    <label className="mb-1 block text-xs font-medium text-gray-700">Email</label>
                                    <input value={editEmail} onChange={(e) => setEditEmail(e.target.value)} type="email"
                                        className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none" />
                                </div>
                                <div>
                                    <label className="mb-1 block text-xs font-medium text-gray-700">Empresa</label>
                                    <input value={editCompany} onChange={(e) => setEditCompany(e.target.value)}
                                        className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none" />
                                </div>
                            </div>
                            <div>
                                <label className="mb-1 block text-xs font-medium text-gray-700">Etiquetas (Enter para añadir)</label>
                                <TagInput tags={editTags} onChange={setEditTags} />
                            </div>
                            <div>
                                <label className="mb-1 block text-xs font-medium text-gray-700">Agente asignado</label>
                                <select value={editAssigned} onChange={(e) => setEditAssigned(e.target.value)}
                                    className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none">
                                    <option value="">Sin asignar</option>
                                    {agents.map((a) => <option key={a.id} value={a.id}>{a.name}</option>)}
                                </select>
                            </div>
                            <div>
                                <label className="mb-1 block text-xs font-medium text-gray-700">Notas</label>
                                <textarea value={editNotes} onChange={(e) => setEditNotes(e.target.value)} rows={3}
                                    className="w-full resize-none rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none" />
                            </div>
                        </div>

                        <div className="flex justify-end gap-2 border-t border-gray-100 px-5 py-3">
                            <button onClick={() => setEditing(false)} className="rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                                Cancelar
                            </button>
                            <button onClick={saveEdit} disabled={saving}
                                className="flex items-center gap-1.5 rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700 disabled:opacity-50">
                                {saving ? <div className="h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent" /> : <Check className="h-4 w-4" />}
                                Guardar
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </AppLayout>
    );
}

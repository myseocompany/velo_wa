import AppLayout from '@/Layouts/AppLayout';
import { useTenantChannel, useTenantPresence } from '@/hooks/useEcho';
import { Conversation, ConversationStatus, Message, PageProps, User } from '@/types';
import { usePage } from '@inertiajs/react';
import axios from 'axios';
import {
    CheckCircle,
    ChevronDown,
    Info,
    MessageSquare,
    Plus,
    RotateCcw,
    UserCheck,
    X,
} from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import ContactAvatar from './Partials/ContactAvatar';
import ContactPanel from './Partials/ContactPanel';
import ConversationList from './Partials/ConversationList';
import MessageThread from './Partials/MessageThread';

interface Props {
    activeConversationId?: string;
}

type ConversationUpdatedPayload = Conversation;
interface MessageReceivedPayload extends Message { conversation_id: string; }

// ─── Status filter tabs ───────────────────────────────────────────────────────

const STATUS_TABS: { value: ConversationStatus | 'all'; label: string }[] = [
    { value: 'all',     label: 'Todas' },
    { value: 'open',    label: 'Abiertas' },
    { value: 'pending', label: 'Pendientes' },
    { value: 'closed',  label: 'Cerradas' },
];

// ─── Notification sound (Web Audio API) ───────────────────────────────────────

function playNotificationSound() {
    try {
        const ctx = new AudioContext();
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();

        osc.connect(gain);
        gain.connect(ctx.destination);

        osc.type = 'sine';
        osc.frequency.setValueAtTime(880, ctx.currentTime);
        osc.frequency.exponentialRampToValueAtTime(660, ctx.currentTime + 0.1);

        gain.gain.setValueAtTime(0.3, ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.3);

        osc.start(ctx.currentTime);
        osc.stop(ctx.currentTime + 0.3);

        osc.onended = () => ctx.close();
    } catch {
        // AudioContext not supported or blocked — silently ignore
    }
}

// ─── Assign dropdown ──────────────────────────────────────────────────────────

interface AssignDropdownProps {
    conversation: Conversation;
    agents: User[];
    onlineUserIds: Set<string>;
    onAssigned: (conv: Conversation) => void;
}

function AssignDropdown({ conversation, agents, onlineUserIds, onAssigned }: AssignDropdownProps) {
    const [open, setOpen]       = useState(false);
    const [loading, setLoading] = useState(false);
    const dropRef               = useRef<HTMLDivElement>(null);

    useEffect(() => {
        function handleClick(e: MouseEvent) {
            if (dropRef.current && !dropRef.current.contains(e.target as Node)) {
                setOpen(false);
            }
        }
        document.addEventListener('mousedown', handleClick);
        return () => document.removeEventListener('mousedown', handleClick);
    }, []);

    async function assign(userId: string | null) {
        setOpen(false);
        setLoading(true);
        try {
            const res = await axios.patch<{ data: Conversation }>(
                `/api/v1/conversations/${conversation.id}/assign`,
                { assigned_to: userId },
            );
            onAssigned(res.data.data);
        } finally {
            setLoading(false);
        }
    }

    const currentName = conversation.assignee?.name ?? 'Sin asignar';

    return (
        <div ref={dropRef} className="relative">
            <button
                onClick={() => setOpen(!open)}
                disabled={loading}
                className="flex items-center gap-1.5 rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50"
            >
                <UserCheck className="h-3.5 w-3.5 text-gray-500" />
                <span className="max-w-[100px] truncate">{currentName}</span>
                <ChevronDown className="h-3 w-3 text-gray-400" />
            </button>

            {open && (
                <div className="absolute right-0 top-full z-20 mt-1 w-48 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-lg">
                    <button
                        onClick={() => assign(null)}
                        className="flex w-full items-center px-3 py-2 text-sm text-gray-500 hover:bg-gray-50"
                    >
                        Sin asignar
                    </button>
                    <div className="h-px bg-gray-100" />
                    {agents.map((agent) => {
                        const isOnline = onlineUserIds.has(agent.id);
                        return (
                            <button
                                key={agent.id}
                                onClick={() => assign(agent.id)}
                                className={`flex w-full items-center gap-2 px-3 py-2 text-sm hover:bg-gray-50 ${
                                    conversation.assigned_to === agent.id ? 'font-semibold text-ari-600' : 'text-gray-800'
                                }`}
                            >
                                <span
                                    className={`h-1.5 w-1.5 flex-shrari-0 rounded-full ${isOnline ? 'bg-green-500' : 'bg-gray-300'}`}
                                />
                                {agent.name}
                            </button>
                        );
                    })}
                </div>
            )}
        </div>
    );
}

interface CreateConversationModalProps {
    agents: User[];
    currentUser: User;
    onClose: () => void;
    onCreated: (conversation: Conversation) => void;
}

function CreateConversationModal({
    agents,
    currentUser,
    onClose,
    onCreated,
}: CreateConversationModalProps) {
    const [phone, setPhone]           = useState('');
    const [name, setName]             = useState('');
    const [email, setEmail]           = useState('');
    const [company, setCompany]       = useState('');
    const [assignedTo, setAssignedTo] = useState(currentUser.id);
    const [saving, setSaving]         = useState(false);
    const [errors, setErrors]         = useState<Record<string, string>>({});

    async function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        setSaving(true);
        setErrors({});

        try {
            const res = await axios.post<{ data: Conversation }>('/api/v1/conversations', {
                phone:       phone.trim(),
                name:        name.trim() || null,
                email:       email.trim() || null,
                company:     company.trim() || null,
                assigned_to: assignedTo || null,
            });

            onCreated(res.data.data);
        } catch (err: unknown) {
            if (axios.isAxiosError(err) && err.response?.data?.errors) {
                const nextErrors: Record<string, string> = {};
                for (const [field, value] of Object.entries(err.response.data.errors)) {
                    nextErrors[field] = Array.isArray(value) ? (value as string[])[0] : String(value);
                }
                setErrors(nextErrors);
            } else {
                setErrors({ form: 'No se pudo crear la conversación. Intenta de nuevo.' });
            }
        } finally {
            setSaving(false);
        }
    }

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div className="w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-xl">
                <div className="flex items-center justify-between border-b border-gray-100 px-5 py-4">
                    <div>
                        <h3 className="font-semibold text-gray-900">Nueva conversación</h3>
                        <p className="mt-1 text-xs text-gray-500">
                            Si el teléfono ya existe, se reutilizará el contacto actual.
                        </p>
                    </div>
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
                            {errors.email && <p className="mt-0.5 text-xs text-red-500">{errors.email}</p>}
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
                            {agents.map((agent) => (
                                <option key={agent.id} value={agent.id}>
                                    {agent.name}
                                </option>
                            ))}
                        </select>
                        {errors.assigned_to && <p className="mt-0.5 text-xs text-red-500">{errors.assigned_to}</p>}
                    </div>

                    {errors.form && <p className="text-sm text-red-500">{errors.form}</p>}

                    <div className="flex justify-end gap-2 pt-1">
                        <button
                            type="button"
                            onClick={onClose}
                            className="rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                        >
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            disabled={saving || !phone.trim()}
                            className="flex items-center gap-1.5 rounded-lg bg-ari-600 px-4 py-2 text-sm font-medium text-white hover:bg-ari-700 disabled:opacity-50"
                        >
                            {saving
                                ? <div className="h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent" />
                                : <Plus className="h-4 w-4" />}
                            Crear y abrir
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}

// ─── Main component ───────────────────────────────────────────────────────────

export default function InboxIndex({ activeConversationId }: Props) {
    const { auth } = usePage<PageProps>().props;

    const [conversations, setConversations]     = useState<Conversation[]>([]);
    const [activeConv, setActiveConv]           = useState<Conversation | null>(null);
    const [messages, setMessages]               = useState<Message[]>([]);
    const [nextCursor, setNextCursor]           = useState<string | null>(null);
    const [loadingConvs, setLoadingConvs]       = useState(true);
    const [loadingMessages, setLoadingMessages] = useState(false);
    const [statusFilter, setStatusFilter]       = useState<ConversationStatus | 'all'>('all');
    const [search, setSearch]                   = useState('');
    const [agents, setAgents]                   = useState<User[]>([]);
    const [showCreateModal, setShowCreateModal] = useState(false);
    const [showContactPanel, setShowContactPanel] = useState(false);
    const [unreadCounts, setUnreadCounts]       = useState<Record<string, number>>({});
    const [onlineUserIds, setOnlineUserIds]     = useState<Set<string>>(new Set());

    const conversationsRef = useRef<Conversation[]>([]);
    const activeConvRef    = useRef<Conversation | null>(null);
    useEffect(() => { conversationsRef.current = conversations; }, [conversations]);
    useEffect(() => { activeConvRef.current = activeConv; }, [activeConv]);

    const refetchingRef = useRef(false);
    const refetchConversations = useCallback(() => {
        if (refetchingRef.current) return;
        refetchingRef.current = true;
        const params: Record<string, string> = {};
        if (statusFilter !== 'all') params.status = statusFilter;
        if (search) params.search = search;
        axios.get<{ data: Conversation[] }>('/api/v1/conversations', { params })
            .then((res) => setConversations(res.data.data))
            .finally(() => { refetchingRef.current = false; });
    }, [statusFilter, search]);

    // Load conversations when filter changes
    useEffect(() => {
        setLoadingConvs(true);
        const params: Record<string, string> = {};
        if (statusFilter !== 'all') params.status = statusFilter;
        if (search) params.search = search;

        axios.get<{ data: Conversation[] }>('/api/v1/conversations', { params })
            .then((res) => {
                const convs = res.data.data;
                setConversations(convs);
                if (activeConversationId && statusFilter === 'all' && !search) {
                    const found = convs.find((c) => c.id === activeConversationId);
                    if (found) selectConversation(found);
                }
            })
            .finally(() => setLoadingConvs(false));
    }, [statusFilter, search]);

    // Load agents for assignment
    useEffect(() => {
        axios.get<{ data: User[] }>('/api/v1/team/members')
            .then((res) => setAgents(res.data.data));
    }, []);

    async function selectConversation(conv: Conversation) {
        setActiveConv(conv);
        setLoadingMessages(true);
        setMessages([]);
        setNextCursor(null);
        // Clear unread count when opening conversation
        setUnreadCounts((prev) => {
            if (!prev[conv.id]) return prev;
            const next = { ...prev };
            delete next[conv.id];
            return next;
        });
        try {
            const res = await axios.get<{ data: Message[]; links: { next: string | null } }>(
                `/api/v1/conversations/${conv.id}/messages`,
            );
            setMessages([...res.data.data].reverse());
            const next = res.data.links.next
                ? new URL(res.data.links.next).searchParams.get('cursor')
                : null;
            setNextCursor(next);
        } finally {
            setLoadingMessages(false);
        }
    }

    // Real-time: message received
    const handleMessageReceived = useCallback((data: unknown) => {
        const payload = data as MessageReceivedPayload;

        if (conversationsRef.current.findIndex((c) => c.id === payload.conversation_id) === -1) {
            refetchConversations();
        } else {
            setConversations((prev) => {
                const i = prev.findIndex((c) => c.id === payload.conversation_id);
                if (i === -1) return prev;
                const updated: Conversation = {
                    ...prev[i],
                    last_message_at: payload.created_at,
                    last_message: { body: payload.body, direction: payload.direction, created_at: payload.created_at, media_type: payload.media_type },
                };
                const next = [...prev];
                next.splice(i, 1);
                return [updated, ...next];
            });
        }

        // Increment unread + play sound for inbound messages on inactive conversations
        const isActiveConv = activeConvRef.current?.id === payload.conversation_id;
        if (payload.direction === 'in' && !isActiveConv) {
            setUnreadCounts((prev) => ({
                ...prev,
                [payload.conversation_id]: (prev[payload.conversation_id] ?? 0) + 1,
            }));
            playNotificationSound();
        }

        if (isActiveConv) {
            setMessages((prev) => {
                const exists = prev.some((m) => m.id === payload.id);
                if (exists) return prev.map((m) => (m.id === payload.id ? (payload as Message) : m));
                return [...prev, payload as Message];
            });
        }
    }, [refetchConversations]);

    // Real-time: conversation updated
    const handleConversationUpdated = useCallback((data: unknown) => {
        const payload = data as ConversationUpdatedPayload;
        setConversations((prev) => {
            const idx = prev.findIndex((c) => c.id === payload.id);
            if (idx === -1) return [payload, ...prev];
            const updated: Conversation = { ...prev[idx], ...payload };
            const next = [...prev];
            next.splice(idx, 1);
            return [updated, ...next];
        });
        setActiveConv((prev) => (prev?.id === payload.id ? { ...prev, ...payload } : prev));
    }, []);

    useTenantChannel(auth.tenant.id, 'message.received', handleMessageReceived);
    useTenantChannel(auth.tenant.id, 'conversation.updated', handleConversationUpdated);

    // Online presence
    useTenantPresence(
        auth.tenant.id,
        useCallback((user: { id: string }) => {
            setOnlineUserIds((prev) => new Set([...prev, user.id]));
        }, []),
        useCallback((user: { id: string }) => {
            setOnlineUserIds((prev) => {
                const next = new Set(prev);
                next.delete(user.id);
                return next;
            });
        }, []),
        useCallback((users: { id: string }[]) => {
            setOnlineUserIds(new Set(users.map((u) => u.id)));
        }, []),
    );

    function handleMessageSent(message: Message) {
        setMessages((prev) => [...prev, message]);
    }

    function handleLoadOlderMessages(older: Message[], newNextCursor: string | null) {
        setMessages((prev) => [...older, ...prev]);
        setNextCursor(newNextCursor);
    }

    async function closeConversation() {
        if (!activeConv) return;
        const res = await axios.patch<{ data: Conversation }>(`/api/v1/conversations/${activeConv.id}/close`);
        setActiveConv(res.data.data);
        updateConvInList(res.data.data);
    }

    async function reopenConversation() {
        if (!activeConv) return;
        const res = await axios.patch<{ data: Conversation }>(`/api/v1/conversations/${activeConv.id}/reopen`);
        setActiveConv(res.data.data);
        updateConvInList(res.data.data);
    }

    function updateConvInList(conv: Conversation) {
        setConversations((prev) => prev.map((c) => (c.id === conv.id ? conv : c)));
    }

    function handleAssigned(conv: Conversation) {
        setActiveConv(conv);
        updateConvInList(conv);
    }

    function handleConversationCreated(conv: Conversation) {
        setShowCreateModal(false);
        setShowContactPanel(false);
        setStatusFilter('all');
        setSearch('');
        setConversations((prev) => {
            const idx = prev.findIndex((item) => item.id === conv.id);
            if (idx === -1) {
                return [conv, ...prev];
            }

            const next = [...prev];
            next.splice(idx, 1);
            return [conv, ...next];
        });
        void selectConversation(conv);
    }

    const isClosed = activeConv?.status === 'closed';

    return (
        <AppLayout title="Inbox">
            <div className="flex h-full overflow-hidden">
                {/* ── Conversation list sidebar ── */}
                <aside className="flex w-80 flex-shrari-0 flex-col border-r border-gray-200 bg-white">
                    <div className="border-b border-gray-100 px-3 py-3">
                        <button
                            onClick={() => setShowCreateModal(true)}
                            className="flex w-full items-center justify-center gap-2 rounded-lg bg-ari-600 px-4 py-2 text-sm font-medium text-white hover:bg-ari-700"
                        >
                            <Plus className="h-4 w-4" />
                            Nueva conversación
                        </button>
                    </div>

                    {/* Search */}
                    <div className="border-b border-gray-100 px-3 py-2">
                        <input
                            type="search"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Buscar por nombre o teléfono…"
                            className="w-full rounded-lg border border-gray-200 px-3 py-1.5 text-sm placeholder-gray-400 focus:border-ari-500 focus:outline-none"
                        />
                    </div>

                    {/* Status filter tabs */}
                    <div className="flex border-b border-gray-100">
                        {STATUS_TABS.map((tab) => (
                            <button
                                key={tab.value}
                                onClick={() => setStatusFilter(tab.value)}
                                className={`flex-1 py-2 text-xs font-medium transition-colors ${
                                    statusFilter === tab.value
                                        ? 'border-b-2 border-ari-600 text-ari-600'
                                        : 'text-gray-500 hover:text-gray-700'
                                }`}
                            >
                                {tab.label}
                            </button>
                        ))}
                    </div>

                    <div className="flex-1 overflow-y-auto">
                        {loadingConvs ? (
                            <div className="flex items-center justify-center py-16">
                                <div className="h-6 w-6 animate-spin rounded-full border-2 border-ari-600 border-t-transparent" />
                            </div>
                        ) : (
                            <ConversationList
                                conversations={conversations}
                                activeId={activeConv?.id ?? null}
                                unreadCounts={unreadCounts}
                                onSelect={selectConversation}
                            />
                        )}
                    </div>
                </aside>

                {/* ── Main area ── */}
                <main className="flex flex-1 flex-col overflow-hidden">
                    {activeConv ? (
                        <>
                            {/* Thread header */}
                            <div className="flex items-center gap-3 border-b border-gray-200 bg-white px-5 py-3">
                                <ContactAvatar
                                    name={activeConv.contact?.name ?? activeConv.contact?.push_name ?? '?'}
                                    imageUrl={activeConv.contact?.profile_pic_url}
                                    sizeClass="h-9 w-9"
                                />
                                <div className="min-w-0 flex-1">
                                    <p className="truncate text-sm font-medium text-gray-900">
                                        {activeConv.contact?.name ??
                                            activeConv.contact?.push_name ??
                                            activeConv.contact?.phone ??
                                            'Desconocido'}
                                    </p>
                                    <p className="text-xs text-gray-500">{activeConv.contact?.phone}</p>
                                </div>

                                {/* Actions */}
                                <div className="flex items-center gap-2">
                                    <AssignDropdown
                                        conversation={activeConv}
                                        agents={agents}
                                        onlineUserIds={onlineUserIds}
                                        onAssigned={handleAssigned}
                                    />

                                    {isClosed ? (
                                        <button
                                            onClick={reopenConversation}
                                            className="flex items-center gap-1.5 rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50"
                                        >
                                            <RotateCcw className="h-3.5 w-3.5" />
                                            Reabrir
                                        </button>
                                    ) : (
                                        <button
                                            onClick={closeConversation}
                                            className="flex items-center gap-1.5 rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50"
                                        >
                                            <CheckCircle className="h-3.5 w-3.5 text-green-600" />
                                            Cerrar
                                        </button>
                                    )}

                                    <button
                                        onClick={() => setShowContactPanel(!showContactPanel)}
                                        className={`rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 ${showContactPanel ? 'bg-gray-100 text-ari-600' : ''}`}
                                        title="Panel de contacto"
                                    >
                                        <Info className="h-4 w-4" />
                                    </button>
                                </div>
                            </div>

                            {/* Thread area */}
                            <div className="flex flex-1 overflow-hidden">
                                {loadingMessages ? (
                                    <div className="flex flex-1 items-center justify-center">
                                        <div className="h-6 w-6 animate-spin rounded-full border-2 border-ari-600 border-t-transparent" />
                                    </div>
                                ) : (
                                    <MessageThread
                                        conversationId={activeConv.id}
                                        messages={messages}
                                        nextCursor={nextCursor}
                                        onMessageSent={handleMessageSent}
                                        onLoadOlderMessages={handleLoadOlderMessages}
                                    />
                                )}

                                {/* Contact info panel */}
                                {showContactPanel && (
                                    <ContactPanel
                                        conversation={activeConv}
                                        onClose={() => setShowContactPanel(false)}
                                    />
                                )}
                            </div>
                        </>
                    ) : (
                        <div className="flex flex-1 flex-col items-center justify-center gap-3 text-gray-400">
                            <MessageSquare className="h-12 w-12" />
                            <p className="text-sm">Selecciona una conversación</p>
                        </div>
                    )}
                </main>
            </div>

            {showCreateModal && (
                <CreateConversationModal
                    agents={agents}
                    currentUser={auth.user}
                    onClose={() => setShowCreateModal(false)}
                    onCreated={handleConversationCreated}
                />
            )}
        </AppLayout>
    );
}

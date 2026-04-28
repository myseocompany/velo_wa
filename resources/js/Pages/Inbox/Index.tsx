import AiAgentToggle from '@/Components/Inbox/AiAgentToggle';
import AppLayout from '@/Layouts/AppLayout';
import { useTenantChannel, useTenantPresence } from '@/hooks/useEcho';
import { AiAgent, Conversation, ConversationStatus, Message, PageProps, User, WhatsAppLine } from '@/types';
import { usePage } from '@inertiajs/react';
import axios from 'axios';
import {
    ArrowLeft,
    CheckCircle,
    ChevronDown,
    Info,
    MessageSquare,
    Plus,
    RotateCcw,
    Trash2,
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
interface CursorPaginatedResponse<T> {
    data: T[];
    links: {
        next: string | null;
    };
}

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
    lines: WhatsAppLine[];
    currentUser: User;
    onClose: () => void;
    onCreated: (conversation: Conversation) => void;
}

function CreateConversationModal({
    agents,
    lines,
    currentUser,
    onClose,
    onCreated,
}: CreateConversationModalProps) {
    const connectedLines = lines.filter((l) => l.status === 'connected');
    const defaultLine    = connectedLines.find((l) => l.is_default) ?? connectedLines[0] ?? null;
    const multiLine      = connectedLines.length > 1;
    const noLines        = connectedLines.length === 0;

    const [phone, setPhone]                   = useState('');
    const [name, setName]                     = useState('');
    const [email, setEmail]                   = useState('');
    const [company, setCompany]               = useState('');
    const [assignedTo, setAssignedTo]         = useState(currentUser.id);
    const [whatsappLineId, setWhatsappLineId] = useState(defaultLine?.id ?? '');
    const [saving, setSaving]                 = useState(false);
    const [errors, setErrors]                 = useState<Record<string, string>>({});

    async function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        setSaving(true);
        setErrors({});

        try {
            const res = await axios.post<{ data: Conversation }>('/api/v1/conversations', {
                phone:              phone.trim(),
                name:               name.trim() || null,
                email:              email.trim() || null,
                company:            company.trim() || null,
                assigned_to:        assignedTo || null,
                whatsapp_line_id:   whatsappLineId || null,
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

                {noLines ? (
                    <div className="px-5 py-8 text-center">
                        <p className="text-sm font-medium text-gray-700">Sin líneas de WhatsApp conectadas</p>
                        <p className="mt-1 text-xs text-gray-400">
                            Conecta una línea en{' '}
                            <a href="/settings/whatsapp" className="text-ari-600 hover:underline">
                                Ajustes › WhatsApp
                            </a>{' '}
                            para iniciar conversaciones.
                        </p>
                    </div>
                ) : (
                    <form onSubmit={handleSubmit} className="space-y-4 px-5 py-4">
                        {/* Line selector — only when >1 connected line */}
                        {multiLine && (
                            <div>
                                <label className="mb-1 block text-xs font-medium text-gray-700">Línea de WhatsApp</label>
                                <select
                                    value={whatsappLineId}
                                    onChange={(e) => setWhatsappLineId(e.target.value)}
                                    className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-ari-500 focus:outline-none"
                                >
                                    {connectedLines.map((line) => (
                                        <option key={line.id} value={line.id}>
                                            {line.label}{line.phone ? ` · ...${line.phone.slice(-4)}` : ''}
                                            {line.is_default ? ' (predeterminada)' : ''}
                                        </option>
                                    ))}
                                </select>
                            </div>
                        )}

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

                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
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

                        <div className="flex flex-col-reverse gap-2 pt-1 sm:flex-row sm:justify-end">
                            <button
                                type="button"
                                onClick={onClose}
                                className="min-h-11 w-full rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 sm:w-auto"
                            >
                                Cancelar
                            </button>
                            <button
                                type="submit"
                                disabled={saving || !phone.trim()}
                                className="flex min-h-11 w-full items-center justify-center gap-1.5 rounded-lg bg-ari-600 px-4 py-2 text-sm font-medium text-white hover:bg-ari-700 disabled:opacity-50 sm:w-auto"
                            >
                                {saving
                                    ? <div className="h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent" />
                                    : <Plus className="h-4 w-4" />}
                                Crear y abrir
                            </button>
                        </div>
                    </form>
                )}
            </div>
        </div>
    );
}

// ─── Main component ───────────────────────────────────────────────────────────

export default function InboxIndex({ activeConversationId }: Props) {
    const CONVERSATION_PAGE_SIZE = 25;
    const { auth } = usePage<PageProps>().props;

    const [conversations, setConversations]     = useState<Conversation[]>([]);
    const [activeConv, setActiveConv]           = useState<Conversation | null>(null);
    const [messages, setMessages]               = useState<Message[]>([]);
    const [nextCursor, setNextCursor]           = useState<string | null>(null);
    const [loadingConvs, setLoadingConvs]       = useState(true);
    const [loadingMoreConvs, setLoadingMoreConvs] = useState(false);
    const [loadingMessages, setLoadingMessages] = useState(false);
    const [conversationsNextCursor, setConversationsNextCursor] = useState<string | null>(null);
    const [statusFilter, setStatusFilter]       = useState<ConversationStatus | 'all'>('all');
    const [search, setSearch]                   = useState('');
    const [agents, setAgents]                   = useState<User[]>([]);
    const [lines, setLines]                     = useState<WhatsAppLine[]>([]);
    const [lineFilter, setLineFilter]           = useState<string | null>(() =>
        new URLSearchParams(window.location.search).get('line_id'),
    );
    const [showCreateModal, setShowCreateModal] = useState(false);
    const [showContactPanel, setShowContactPanel] = useState(false);
    const [unreadCounts, setUnreadCounts]       = useState<Record<string, number>>({});
    const [onlineUserIds, setOnlineUserIds]     = useState<Set<string>>(new Set());
    const [aiAgentConfig, setAiAgentConfig] = useState<AiAgent | null>(null);
    const [togglingAi, setTogglingAi] = useState(false);
    const [deletingConversation, setDeletingConversation] = useState(false);

    const conversationsRef = useRef<Conversation[]>([]);
    const activeConvRef    = useRef<Conversation | null>(null);
    const pendingRouteConversationIdRef = useRef<string | null>(activeConversationId ?? null);
    useEffect(() => { conversationsRef.current = conversations; }, [conversations]);
    useEffect(() => { activeConvRef.current = activeConv; }, [activeConv]);
    useEffect(() => { pendingRouteConversationIdRef.current = activeConversationId ?? null; }, [activeConversationId]);

    const extractNextCursor = useCallback((nextUrl: string | null | undefined) => {
        if (!nextUrl) return null;

        return new URL(nextUrl).searchParams.get('cursor');
    }, []);

    const buildConversationParams = useCallback((options?: {
        cursor?: string | null;
        limit?: number;
    }) => {
        const params: Record<string, string> = {
            limit: String(options?.limit ?? CONVERSATION_PAGE_SIZE),
        };

        if (statusFilter !== 'all') params.status = statusFilter;
        if (search) params.search = search;
        if (lineFilter && lines.some((line) => line.id === lineFilter)) {
            params.whatsapp_line_id = lineFilter;
        }
        if (options?.cursor) params.cursor = options.cursor;

        return params;
    }, [CONVERSATION_PAGE_SIZE, lineFilter, lines, search, statusFilter]);

    const mergeConversationPages = useCallback((current: Conversation[], incoming: Conversation[]) => {
        const byId = new Map(current.map((conversation) => [conversation.id, conversation]));

        incoming.forEach((conversation) => {
            const previous = byId.get(conversation.id);
            byId.set(conversation.id, previous ? { ...previous, ...conversation } : conversation);
        });

        return [...byId.values()].sort((a, b) => {
            const aTime = a.last_message_at ? new Date(a.last_message_at).getTime() : 0;
            const bTime = b.last_message_at ? new Date(b.last_message_at).getTime() : 0;

            if (bTime !== aTime) return bTime - aTime;

            return new Date(b.created_at).getTime() - new Date(a.created_at).getTime();
        });
    }, []);

    const fetchConversations = useCallback(async (options?: {
        cursor?: string | null;
        limit?: number;
    }) => {
        const res = await axios.get<CursorPaginatedResponse<Conversation>>('/api/v1/conversations', {
            params: buildConversationParams(options),
        });

        return {
            conversations: res.data.data,
            nextCursor: extractNextCursor(res.data.links?.next),
        };
    }, [buildConversationParams, extractNextCursor]);

    const refetchingRef = useRef(false);
    const refetchConversations = useCallback(() => {
        if (refetchingRef.current) return;
        refetchingRef.current = true;
        const loadedCount = Math.max(conversationsRef.current.length, CONVERSATION_PAGE_SIZE);

        fetchConversations({ limit: loadedCount })
            .then(({ conversations: refreshedConversations, nextCursor: refreshedNextCursor }) => {
                setConversations(refreshedConversations);
                setConversationsNextCursor(refreshedNextCursor);
            })
            .finally(() => { refetchingRef.current = false; });
    }, [CONVERSATION_PAGE_SIZE, fetchConversations]);

    const loadMoreConversations = useCallback(async () => {
        if (loadingMoreConvs || !conversationsNextCursor) return;

        setLoadingMoreConvs(true);

        try {
            const { conversations: moreConversations, nextCursor: nextConversationsCursor } = await fetchConversations({
                cursor: conversationsNextCursor,
                limit: CONVERSATION_PAGE_SIZE,
            });

            setConversations((prev) => mergeConversationPages(prev, moreConversations));
            setConversationsNextCursor(nextConversationsCursor);
        } finally {
            setLoadingMoreConvs(false);
        }
    }, [CONVERSATION_PAGE_SIZE, conversationsNextCursor, fetchConversations, loadingMoreConvs, mergeConversationPages]);

    const loadLatestMessages = useCallback(async (conversationId: string) => {
        const res = await axios.get<{ data: Message[]; links: { next: string | null } }>(
            `/api/v1/conversations/${conversationId}/messages`,
        );

        return {
            messages: [...res.data.data].reverse(),
            nextCursor: res.data.links.next
                ? new URL(res.data.links.next).searchParams.get('cursor')
                : null,
        };
    }, []);

    const mergeMessages = useCallback((current: Message[], latest: Message[]): Message[] => {
        const byId = new Map(current.map((message) => [message.id, message]));

        latest.forEach((message) => {
            const previous = byId.get(message.id);
            byId.set(message.id, previous ? { ...previous, ...message } : message);
        });

        return [...byId.values()].sort(
            (a, b) => new Date(a.created_at).getTime() - new Date(b.created_at).getTime(),
        );
    }, []);

    const syncInboxUrl = useCallback((conversationId: string | null = null, params = new URLSearchParams(window.location.search)) => {
        const qs = params.toString();
        const basePath = conversationId ? `/inbox/${conversationId}` : '/inbox';

        window.history.replaceState({}, '', qs ? `${basePath}?${qs}` : basePath);
    }, []);

    // Load conversations when filter changes
    useEffect(() => {
        setLoadingConvs(true);
        setConversationsNextCursor(null);

        fetchConversations({ limit: CONVERSATION_PAGE_SIZE })
            .then(({ conversations: convs, nextCursor: firstPageNextCursor }) => {
                setConversations(convs);
                setConversationsNextCursor(firstPageNextCursor);
                const pendingRouteConversationId = pendingRouteConversationIdRef.current;

                if (pendingRouteConversationId && statusFilter === 'all' && !search && !activeConvRef.current) {
                    const found = convs.find((c) => c.id === pendingRouteConversationId);
                    pendingRouteConversationIdRef.current = null;
                    if (found) selectConversation(found);
                }
            })
            .finally(() => setLoadingConvs(false));
    }, [CONVERSATION_PAGE_SIZE, fetchConversations, search, statusFilter]);

    // Load AI agent config (global toggle state for per-conversation behavior)
    useEffect(() => {
        axios.get<{ data: AiAgent; available_models: string[] }>('/api/v1/ai-agent')
            .then((res) => setAiAgentConfig(res.data.data))
            .catch(() => setAiAgentConfig(null));
    }, []);

    // Load agents for assignment
    useEffect(() => {
        axios.get<{ data: User[] }>('/api/v1/team/members')
            .then((res) => setAgents(res.data.data));
    }, []);

    // Load WhatsApp lines (for filter + selector)
    useEffect(() => {
        axios.get<{ data: WhatsAppLine[] }>('/api/v1/whatsapp/lines')
            .then((res) => setLines(res.data.data))
            .catch(() => setLines([]));
    }, []);

    // Clear line filter if the selected line no longer exists (e.g. deleted)
    useEffect(() => {
        if (!lineFilter || lines.length === 0) return;
        if (!lines.some((l) => l.id === lineFilter)) {
            setLineFilter(null);
        }
    }, [lineFilter, lines]);

    // Sync line filter to URL query param
    useEffect(() => {
        const params = new URLSearchParams(window.location.search);
        if (lineFilter) {
            params.set('line_id', lineFilter);
        } else {
            params.delete('line_id');
        }
        syncInboxUrl(activeConv?.id ?? null, params);
    }, [activeConv?.id, lineFilter, syncInboxUrl]);

    async function selectConversation(conv: Conversation) {
        setActiveConv(conv);
        syncInboxUrl(conv.id);
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
            const { messages: latestMessages, nextCursor: latestNextCursor } = await loadLatestMessages(conv.id);
            setMessages(latestMessages);
            setNextCursor(latestNextCursor);
        } finally {
            setLoadingMessages(false);
        }
    }

    // Poll as a fallback when a websocket reconnect or private-channel auth is missed.
    useEffect(() => {
        const intervalId = window.setInterval(() => {
            if (document.visibilityState !== 'visible') return;
            refetchConversations();
        }, 10000);

        return () => window.clearInterval(intervalId);
    }, [refetchConversations]);

    useEffect(() => {
        if (!activeConv) return;

        const intervalId = window.setInterval(() => {
            if (document.visibilityState !== 'visible') return;

            loadLatestMessages(activeConv.id)
                .then(({ messages: latestMessages, nextCursor: latestNextCursor }) => {
                    setMessages((prev) => mergeMessages(prev, latestMessages));
                    setNextCursor(latestNextCursor);
                })
                .catch(() => {
                    // Ignore transient polling errors; realtime remains the primary path.
                });
        }, 5000);

        return () => window.clearInterval(intervalId);
    }, [activeConv, loadLatestMessages, mergeMessages]);

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
        // Optimistically mark as sent so the spinner clears immediately.
        // The real status (sent/delivered/read/failed) will arrive via Reverb.
        setMessages((prev) => [...prev, { ...message, status: 'sent' as Message['status'] }]);
    }

    function handleLoadOlderMessages(older: Message[], newNextCursor: string | null) {
        setMessages((prev) => [...older, ...prev]);
        setNextCursor(newNextCursor);
    }

    async function toggleAiForConversation() {
        if (!activeConv || !aiAgentConfig) return;

        const currentEffective = activeConv.ai_agent_enabled !== null
            ? activeConv.ai_agent_enabled
            : aiAgentConfig.is_enabled;

        setTogglingAi(true);
        try {
            const res = await axios.patch<{ data: Conversation }>(
                `/api/v1/conversations/${activeConv.id}/ai-agent-toggle`,
                { enabled: !currentEffective },
            );
            setActiveConv(res.data.data);
            updateConvInList(res.data.data);
        } finally {
            setTogglingAi(false);
        }
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

    async function deleteConversation() {
        if (!activeConv) return;
        if (!window.confirm('¿Eliminar esta conversación? Esta acción no se puede deshacer.')) return;

        const deletedId = activeConv.id;
        setDeletingConversation(true);
        try {
            await axios.delete(`/api/v1/conversations/${deletedId}`);
            setConversations((prev) => prev.filter((c) => c.id !== deletedId));
            setUnreadCounts((prev) => {
                const next = { ...prev };
                delete next[deletedId];
                return next;
            });
            setActiveConv(null);
            setMessages([]);
            setShowContactPanel(false);
        } finally {
            setDeletingConversation(false);
        }
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
    const canDeleteConversation = auth.user.role === 'admin' || auth.user.role === 'owner';

    function handleBackToList() {
        setActiveConv(null);
        setMessages([]);
        setShowContactPanel(false);
        syncInboxUrl(null);
    }

    return (
        <AppLayout title="Inbox">
            <div className="flex h-full overflow-hidden">
                {/* ── Conversation list sidebar ── */}
                <aside className={`flex w-full flex-shrink-0 flex-col border-r border-gray-200 bg-white md:w-80 ${activeConv ? 'hidden md:flex' : 'flex'}`}>
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

                    {/* Line filter — only when tenant has >1 line */}
                    {lines.length > 1 && (
                        <div className="border-b border-gray-100 px-3 py-2">
                            <select
                                value={lineFilter ?? ''}
                                onChange={(e) => setLineFilter(e.target.value || null)}
                                className="w-full rounded-lg border border-gray-200 px-3 py-1.5 text-sm text-gray-700 focus:border-ari-500 focus:outline-none"
                                aria-label="Filtrar por línea de WhatsApp"
                            >
                                <option value="">Todas las líneas</option>
                                {lines.map((line) => (
                                    <option key={line.id} value={line.id}>
                                        {line.label}{line.phone ? ` · ...${line.phone.slice(-4)}` : ''}
                                    </option>
                                ))}
                            </select>
                        </div>
                    )}

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
                                lines={lines}
                                hasMore={conversationsNextCursor !== null}
                                loadingMore={loadingMoreConvs}
                                onLoadMore={loadMoreConversations}
                                onSelect={selectConversation}
                            />
                        )}
                    </div>
                </aside>

                {/* ── Main area ── */}
                <main className={`flex flex-1 flex-col overflow-hidden ${activeConv ? 'flex' : 'hidden md:flex'}`}>
                    {activeConv ? (
                        <>
                            {/* Thread header */}
                            <div className="flex items-center gap-3 border-b border-gray-200 bg-white px-3 py-3 md:px-5">
                                {/* Back button — mobile only */}
                                <button
                                    onClick={handleBackToList}
                                    className="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-lg text-gray-500 hover:bg-gray-100 md:hidden"
                                >
                                    <ArrowLeft className="h-5 w-5" />
                                </button>
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
                                    {lines.length > 1 && activeConv.whatsapp_line && (
                                        <p className="text-xs text-gray-400">
                                            via {activeConv.whatsapp_line.label}
                                            {activeConv.whatsapp_line.phone ? ` · ${activeConv.whatsapp_line.phone}` : ''}
                                        </p>
                                    )}
                                </div>

                                {/* Actions */}
                                <div className="flex items-center gap-2">
                                    <div className="hidden md:block">
                                        <AssignDropdown
                                            conversation={activeConv}
                                            agents={agents}
                                            onlineUserIds={onlineUserIds}
                                            onAssigned={handleAssigned}
                                        />
                                    </div>

                                    {aiAgentConfig && (
                                        <AiAgentToggle
                                            globalEnabled={aiAgentConfig.is_enabled}
                                            conversationOverride={activeConv.ai_agent_enabled}
                                            loading={togglingAi}
                                            onToggle={toggleAiForConversation}
                                        />
                                    )}

                                    {isClosed ? (
                                        <button
                                            onClick={reopenConversation}
                                            className="hidden items-center gap-1.5 rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 md:flex"
                                        >
                                            <RotateCcw className="h-3.5 w-3.5" />
                                            Reabrir
                                        </button>
                                    ) : (
                                        <button
                                            onClick={closeConversation}
                                            className="hidden items-center gap-1.5 rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 md:flex"
                                        >
                                            <CheckCircle className="h-3.5 w-3.5 text-green-600" />
                                            Cerrar
                                        </button>
                                    )}

                                    {canDeleteConversation && (
                                        <button
                                            onClick={deleteConversation}
                                            disabled={deletingConversation}
                                            className="hidden items-center gap-1.5 rounded-lg border border-red-200 bg-red-50 px-3 py-1.5 text-xs font-medium text-red-700 hover:bg-red-100 disabled:opacity-50 md:flex"
                                        >
                                            <Trash2 className="h-3.5 w-3.5" />
                                            {deletingConversation ? 'Eliminando…' : 'Eliminar'}
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

                                {/* Contact info panel — overlay on mobile, side panel on md+ */}
                                {showContactPanel && (
                                    <>
                                        {/* Mobile overlay backdrop */}
                                        <div
                                            className="fixed inset-0 z-30 bg-black/40 md:hidden"
                                            onClick={() => setShowContactPanel(false)}
                                        />
                                        <div className="fixed inset-y-0 right-0 z-40 w-full max-w-sm md:relative md:inset-auto md:z-auto md:w-72 md:max-w-none">
                                            <ContactPanel
                                                conversation={activeConv}
                                                onClose={() => setShowContactPanel(false)}
                                            />
                                        </div>
                                    </>
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
                    lines={lines}
                    currentUser={auth.user}
                    onClose={() => setShowCreateModal(false)}
                    onCreated={handleConversationCreated}
                />
            )}
        </AppLayout>
    );
}

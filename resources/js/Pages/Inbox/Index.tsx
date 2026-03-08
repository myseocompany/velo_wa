import AppLayout from '@/Layouts/AppLayout';
import { useTenantChannel } from '@/hooks/useEcho';
import { Conversation, ConversationStatus, Message, PageProps, User } from '@/types';
import { usePage } from '@inertiajs/react';
import axios from 'axios';
import {
    CheckCircle,
    ChevronDown,
    Info,
    MessageSquare,
    RotateCcw,
    UserCheck,
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

// ─── Assign dropdown ──────────────────────────────────────────────────────────

interface AssignDropdownProps {
    conversation: Conversation;
    agents: User[];
    onAssigned: (conv: Conversation) => void;
}

function AssignDropdown({ conversation, agents, onAssigned }: AssignDropdownProps) {
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
                    {agents.map((agent) => (
                        <button
                            key={agent.id}
                            onClick={() => assign(agent.id)}
                            className={`flex w-full items-center gap-2 px-3 py-2 text-sm hover:bg-gray-50 ${
                                conversation.assigned_to === agent.id ? 'font-semibold text-brand-600' : 'text-gray-800'
                            }`}
                        >
                            <span
                                className={`h-1.5 w-1.5 flex-shrink-0 rounded-full ${agent.is_online ? 'bg-green-500' : 'bg-gray-300'}`}
                            />
                            {agent.name}
                        </button>
                    ))}
                </div>
            )}
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
    const [showContactPanel, setShowContactPanel] = useState(false);

    const conversationsRef = useRef<Conversation[]>([]);
    useEffect(() => { conversationsRef.current = conversations; }, [conversations]);

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
        try {
            const res = await axios.get<{ data: Message[]; links: { next: string | null } }>(
                `/api/v1/conversations/${conv.id}/messages`,
            );
            // API returns DESC (newest first) — reverse for chronological display
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
        if (activeConv?.id === payload.conversation_id) {
            setMessages((prev) => {
                const exists = prev.some((m) => m.id === payload.id);
                if (exists) return prev.map((m) => (m.id === payload.id ? (payload as Message) : m));
                return [...prev, payload as Message];
            });
        }
    }, [activeConv?.id, refetchConversations]);

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
        // Update active conversation if it's the one updated
        setActiveConv((prev) => (prev?.id === payload.id ? { ...prev, ...payload } : prev));
    }, []);

    useTenantChannel(auth.tenant.id, 'message.received', handleMessageReceived);
    useTenantChannel(auth.tenant.id, 'conversation.updated', handleConversationUpdated);

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

    const isClosed = activeConv?.status === 'closed';

    return (
        <AppLayout title="Inbox">
            <div className="flex h-full overflow-hidden">
                {/* ── Conversation list sidebar ── */}
                <aside className="flex w-80 flex-shrink-0 flex-col border-r border-gray-200 bg-white">
                    {/* Search */}
                    <div className="border-b border-gray-100 px-3 py-2">
                        <input
                            type="search"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Buscar por nombre o teléfono…"
                            className="w-full rounded-lg border border-gray-200 px-3 py-1.5 text-sm placeholder-gray-400 focus:border-brand-500 focus:outline-none"
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
                                        ? 'border-b-2 border-brand-600 text-brand-600'
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
                                <div className="h-6 w-6 animate-spin rounded-full border-2 border-brand-600 border-t-transparent" />
                            </div>
                        ) : (
                            <ConversationList
                                conversations={conversations}
                                activeId={activeConv?.id ?? null}
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
                                        className={`rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 ${showContactPanel ? 'bg-gray-100 text-brand-600' : ''}`}
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
                                        <div className="h-6 w-6 animate-spin rounded-full border-2 border-brand-600 border-t-transparent" />
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
        </AppLayout>
    );
}

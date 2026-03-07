import AppLayout from '@/Layouts/AppLayout';
import { useTenantChannel } from '@/hooks/useEcho';
import { Conversation, Message, PageProps } from '@/types';
import { usePage } from '@inertiajs/react';
import axios from 'axios';
import { MessageSquare } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';
import ContactAvatar from './Partials/ContactAvatar';
import ConversationList from './Partials/ConversationList';
import MessageThread from './Partials/MessageThread';

interface Props {
    activeConversationId?: string;
}

// ConversationUpdated broadcasts the full Conversation shape
type ConversationUpdatedPayload = Conversation;

interface MessageReceivedPayload extends Message {
    conversation_id: string;
}

export default function InboxIndex({ activeConversationId }: Props) {
    const { auth } = usePage<PageProps>().props;

    const [conversations, setConversations]             = useState<Conversation[]>([]);
    const [activeConv, setActiveConv]                   = useState<Conversation | null>(null);
    const [messages, setMessages]                       = useState<Message[]>([]);
    const [loadingConvs, setLoadingConvs]               = useState(true);
    const [loadingMessages, setLoadingMessages]         = useState(false);

    // Load conversation list
    useEffect(() => {
        axios.get<{ data: Conversation[] }>('/api/v1/conversations').then((res) => {
            const convs = res.data.data;
            setConversations(convs);
            if (activeConversationId) {
                const found = convs.find((c) => c.id === activeConversationId);
                if (found) selectConversation(found);
            }
        }).finally(() => setLoadingConvs(false));
    }, []);

    // Load messages for active conversation
    async function selectConversation(conv: Conversation) {
        setActiveConv(conv);
        setLoadingMessages(true);
        setMessages([]);
        try {
            const res = await axios.get<{ data: Message[] }>(`/api/v1/conversations/${conv.id}/messages`);
            setMessages(res.data.data);
        } finally {
            setLoadingMessages(false);
        }
    }

    // Real-time: new / updated message
    const handleMessageReceived = useCallback((data: unknown) => {
        const payload = data as MessageReceivedPayload;

        setConversations((prev) => {
            const idx = prev.findIndex((c) => c.id === payload.conversation_id);
            if (idx === -1) {
                return prev;
            }

            const updated: Conversation = {
                ...prev[idx],
                last_message_at: payload.created_at,
                last_message: {
                    body: payload.body,
                    direction: payload.direction,
                    created_at: payload.created_at,
                    media_type: payload.media_type,
                },
            };
            const next = [...prev];
            next.splice(idx, 1);

            return [updated, ...next];
        });

        // Add to thread if viewing that conversation
        if (activeConv?.id === payload.conversation_id) {
            setMessages((prev) => {
                const exists = prev.some((m) => m.id === payload.id);
                if (exists) {
                    return prev.map((m) => (m.id === payload.id ? (payload as Message) : m));
                }
                return [...prev, payload as Message];
            });
        }
    }, [activeConv?.id]);

    // Real-time: conversation list update
    const handleConversationUpdated = useCallback((data: unknown) => {
        const payload = data as ConversationUpdatedPayload;
        setConversations((prev) => {
            const idx = prev.findIndex((c) => c.id === payload.id);
            if (idx === -1) {
                // New conversation: insert directly at the top using broadcast data
                return [payload, ...prev];
            }
            // Existing conversation: merge and bubble to top
            const updated: Conversation = { ...prev[idx], ...payload };
            const next = [...prev];
            next.splice(idx, 1);
            return [updated, ...next];
        });
    }, []);

    useTenantChannel(auth.tenant.id, 'message.received', handleMessageReceived);
    useTenantChannel(auth.tenant.id, 'conversation.updated', handleConversationUpdated);

    function handleMessageSent(message: Message) {
        setMessages((prev) => [...prev, message]);
    }

    return (
        <AppLayout title="Inbox">
            <div className="flex h-full overflow-hidden">
                {/* Conversation list sidebar */}
                <aside className="flex w-80 flex-shrink-0 flex-col border-r border-gray-200 bg-white">
                    <div className="border-b border-gray-200 px-4 py-3">
                        <h2 className="text-sm font-semibold text-gray-900">Conversaciones</h2>
                    </div>
                    <div className="flex-1 overflow-y-auto">
                        {loadingConvs ? (
                            <div className="flex items-center justify-center py-16">
                                <div className="h-6 w-6 animate-spin rounded-full border-2 border-green-600 border-t-transparent" />
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

                {/* Message thread */}
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
                                <div>
                                    <p className="text-sm font-medium text-gray-900">
                                        {activeConv.contact?.name ??
                                            activeConv.contact?.push_name ??
                                            activeConv.contact?.phone ??
                                            'Desconocido'}
                                    </p>
                                    <p className="text-xs text-gray-500">{activeConv.contact?.phone}</p>
                                </div>
                            </div>

                            {loadingMessages ? (
                                <div className="flex flex-1 items-center justify-center">
                                    <div className="h-6 w-6 animate-spin rounded-full border-2 border-green-600 border-t-transparent" />
                                </div>
                            ) : (
                                <MessageThread
                                    conversationId={activeConv.id}
                                    messages={messages}
                                    onMessageSent={handleMessageSent}
                                />
                            )}
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

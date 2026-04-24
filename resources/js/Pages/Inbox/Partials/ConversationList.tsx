import { Conversation, WhatsAppLine } from '@/types';
import { formatDistanceToNow } from 'date-fns';
import { es } from 'date-fns/locale';
import { MessageSquare } from 'lucide-react';
import { useEffect, useRef } from 'react';
import ContactAvatar from './ContactAvatar';

interface Props {
    conversations: Conversation[];
    activeId: string | null;
    unreadCounts: Record<string, number>;
    lines?: WhatsAppLine[];
    hasMore: boolean;
    loadingMore: boolean;
    onLoadMore: () => void;
    onSelect: (conversation: Conversation) => void;
}

function getLastMessagePreview(conversation: Conversation): string {
    const last = conversation.last_message;

    if (!last) {
        return `${conversation.message_count} mensaje${conversation.message_count !== 1 ? 's' : ''}`;
    }

    const body = last.body?.trim();
    if (body) {
        return body;
    }

    if (last.media_type) {
        return 'Archivo adjunto';
    }

    return 'Mensaje sin texto';
}

export default function ConversationList({
    conversations,
    activeId,
    unreadCounts,
    lines = [],
    hasMore,
    loadingMore,
    onLoadMore,
    onSelect,
}: Props) {
    const showLineBadge = lines.length > 1;
    const loadMoreRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        if (!hasMore || !loadMoreRef.current) return;

        const observer = new IntersectionObserver(
            (entries) => {
                if (entries[0].isIntersecting && !loadingMore) {
                    onLoadMore();
                }
            },
            { threshold: 0.1 },
        );

        observer.observe(loadMoreRef.current);
        return () => observer.disconnect();
    }, [hasMore, loadingMore, onLoadMore]);

    if (conversations.length === 0) {
        return (
            <div className="flex flex-col items-center justify-center gap-2 py-16 text-gray-400">
                <MessageSquare className="h-8 w-8" />
                <p className="text-sm">Sin conversaciones</p>
            </div>
        );
    }

    return (
        <>
            <ul className="divide-y divide-gray-100">
                {conversations.map((conv) => {
                const contact     = conv.contact;
                const displayName = contact?.name ?? contact?.push_name ?? contact?.phone ?? 'Desconocido';
                const isActive    = conv.id === activeId;
                const unread      = unreadCounts[conv.id] ?? 0;

                return (
                    <li key={conv.id}>
                        <button
                            onClick={() => onSelect(conv)}
                            className={`flex w-full items-start gap-3 px-4 py-3 text-left hover:bg-gray-50 ${isActive ? 'border-l-2 border-ari-600 bg-ari-50' : ''}`}
                        >
                            <ContactAvatar
                                name={displayName}
                                imageUrl={contact?.profile_pic_url}
                                sizeClass="h-10 w-10"
                            />
                            <div className="min-w-0 flex-1">
                                <div className="flex items-center justify-between">
                                    <span className={`truncate text-sm ${unread > 0 ? 'font-semibold text-gray-900' : 'font-medium text-gray-900'}`}>
                                        {displayName}
                                    </span>
                                    <div className="ml-2 flex flex-shrari-0 items-center gap-1.5">
                                        {conv.last_message_at && (
                                            <span className="text-xs text-gray-400">
                                                {formatDistanceToNow(new Date(conv.last_message_at), {
                                                    addSuffix: true,
                                                    locale: es,
                                                })}
                                            </span>
                                        )}
                                        {unread > 0 && (
                                            <span className="flex h-5 min-w-[20px] items-center justify-center rounded-full bg-ari-600 px-1.5 text-[10px] font-bold text-white">
                                                {unread > 99 ? '99+' : unread}
                                            </span>
                                        )}
                                    </div>
                                </div>
                                <div className="mt-0.5 flex items-center gap-1.5">
                                    <p className={`min-w-0 flex-1 truncate text-xs ${unread > 0 ? 'font-medium text-gray-700' : 'text-gray-500'}`}>
                                        {getLastMessagePreview(conv)}
                                    </p>
                                    {showLineBadge && conv.whatsapp_line && (
                                        <span
                                            className="max-w-[80px] flex-shrink-0 truncate rounded-sm bg-gray-100 px-1 py-0.5 text-[10px] font-medium text-gray-500"
                                            title={conv.whatsapp_line.label}
                                        >
                                            {conv.whatsapp_line.label}
                                        </span>
                                    )}
                                </div>
                            </div>
                        </button>
                    </li>
                );
            })}
            </ul>

            {(hasMore || loadingMore) && (
                <div ref={loadMoreRef} className="flex justify-center px-4 py-4">
                    {loadingMore ? (
                        <div className="h-5 w-5 animate-spin rounded-full border-2 border-ari-600 border-t-transparent" />
                    ) : (
                        <button
                            onClick={onLoadMore}
                            className="text-xs font-medium text-ari-600 hover:underline"
                        >
                            Cargar más conversaciones
                        </button>
                    )}
                </div>
            )}
        </>
    );
}

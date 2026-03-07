import { Conversation } from '@/types';
import { formatDistanceToNow } from 'date-fns';
import { es } from 'date-fns/locale';
import { MessageSquare } from 'lucide-react';

interface Props {
    conversations: Conversation[];
    activeId: string | null;
    onSelect: (conversation: Conversation) => void;
}

function Avatar({ name }: { name: string | null }) {
    const initials = (name ?? '?')
        .split(' ')
        .slice(0, 2)
        .map((w) => w[0])
        .join('')
        .toUpperCase();

    return (
        <div className="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-green-100 text-sm font-semibold text-green-700">
            {initials}
        </div>
    );
}

export default function ConversationList({ conversations, activeId, onSelect }: Props) {
    if (conversations.length === 0) {
        return (
            <div className="flex flex-col items-center justify-center gap-2 py-16 text-gray-400">
                <MessageSquare className="h-8 w-8" />
                <p className="text-sm">Sin conversaciones</p>
            </div>
        );
    }

    return (
        <ul className="divide-y divide-gray-100">
            {conversations.map((conv) => {
                const contact     = conv.contact;
                const displayName = contact?.name ?? contact?.push_name ?? contact?.phone ?? 'Desconocido';
                const isActive    = conv.id === activeId;

                return (
                    <li key={conv.id}>
                        <button
                            onClick={() => onSelect(conv)}
                            className={`flex w-full items-start gap-3 px-4 py-3 text-left hover:bg-gray-50 ${isActive ? 'border-l-2 border-green-600 bg-green-50' : ''}`}
                        >
                            <Avatar name={displayName} />
                            <div className="min-w-0 flex-1">
                                <div className="flex items-center justify-between">
                                    <span className="truncate text-sm font-medium text-gray-900">
                                        {displayName}
                                    </span>
                                    {conv.last_message_at && (
                                        <span className="ml-2 flex-shrink-0 text-xs text-gray-400">
                                            {formatDistanceToNow(new Date(conv.last_message_at), {
                                                addSuffix: true,
                                                locale: es,
                                            })}
                                        </span>
                                    )}
                                </div>
                                <p className="mt-0.5 truncate text-xs text-gray-500">
                                    {conv.message_count} mensaje{conv.message_count !== 1 ? 's' : ''}
                                </p>
                            </div>
                        </button>
                    </li>
                );
            })}
        </ul>
    );
}

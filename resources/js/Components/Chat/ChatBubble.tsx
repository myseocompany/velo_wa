import { Loader2 } from 'lucide-react';

export type ChatRole = 'user' | 'assistant';

interface ChatBubbleProps {
    role: ChatRole;
    content: string;
    meta?: string;
    loading?: boolean;
}

export default function ChatBubble({ role, content, meta, loading = false }: ChatBubbleProps) {
    const isUser = role === 'user';

    return (
        <div className={`flex ${isUser ? 'justify-end' : 'justify-start'}`}>
            <div
                className={`max-w-[82%] rounded-lg px-3 py-2 text-sm shadow-sm ${
                    isUser
                        ? 'bg-ari-600 text-white'
                        : 'border border-gray-100 bg-white text-gray-900'
                }`}
            >
                {loading ? (
                    <div className="flex items-center gap-2 text-gray-500">
                        <Loader2 className="h-4 w-4 animate-spin" />
                        <span>Escribiendo...</span>
                    </div>
                ) : (
                    <p className="whitespace-pre-wrap leading-relaxed">{content}</p>
                )}

                {meta && !loading && (
                    <p className={`mt-1 text-[11px] ${isUser ? 'text-ari-100' : 'text-gray-400'}`}>
                        {meta}
                    </p>
                )}
            </div>
        </div>
    );
}

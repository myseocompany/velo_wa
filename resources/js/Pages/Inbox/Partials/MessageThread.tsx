import { Message, MessageStatus } from '@/types';
import axios from 'axios';
import { Check, CheckCheck, Download, FileText, Image, Loader2, Mic, Play, Send } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

interface Props {
    conversationId: string;
    messages: Message[];
    onMessageSent: (message: Message) => void;
}

function StatusIcon({ status }: { status: MessageStatus }) {
    if (status === 'read')      return <CheckCheck className="h-3.5 w-3.5 text-blue-500" />;
    if (status === 'delivered') return <CheckCheck className="h-3.5 w-3.5 text-gray-400" />;
    if (status === 'sent')      return <Check className="h-3.5 w-3.5 text-gray-400" />;
    if (status === 'failed')    return <span className="text-xs text-red-500">!</span>;
    return <Loader2 className="h-3.5 w-3.5 animate-spin text-gray-300" />;
}

function MediaContent({ message, isOut }: { message: Message; isOut: boolean }) {
    const { media_type, media_url, media_mime_type, media_filename } = message;

    if (!media_type) return null;

    const textColor = isOut ? 'text-brand-100' : 'text-gray-500';

    // Media not yet downloaded
    if (!media_url) {
        return (
            <div className={`flex items-center gap-2 py-1 ${textColor}`}>
                <Loader2 className="h-4 w-4 animate-spin" />
                <span className="text-xs italic">Descargando {media_type}...</span>
            </div>
        );
    }

    switch (media_type) {
        case 'image':
            return (
                <img
                    src={media_url}
                    alt="Imagen"
                    className="max-h-64 max-w-full rounded-lg cursor-pointer"
                    onClick={() => window.open(media_url, '_blank')}
                />
            );
        case 'video':
            return (
                <video
                    src={media_url}
                    controls
                    className="max-h-64 max-w-full rounded-lg"
                    preload="metadata"
                />
            );
        case 'audio':
            return (
                <div className="flex items-center gap-2 py-1">
                    <Mic className={`h-4 w-4 flex-shrink-0 ${isOut ? 'text-brand-100' : 'text-brand-600'}`} />
                    <audio src={media_url} controls preload="metadata" className="h-8 max-w-[240px]" />
                </div>
            );
        case 'document':
            return (
                <a
                    href={media_url}
                    target="_blank"
                    rel="noopener noreferrer"
                    className={`flex items-center gap-2 rounded-lg border px-3 py-2 ${
                        isOut ? 'border-brand-400 hover:bg-brand-600' : 'border-gray-200 hover:bg-gray-50'
                    }`}
                >
                    <FileText className="h-5 w-5 flex-shrink-0" />
                    <span className="truncate text-xs font-medium">
                        {media_filename ?? 'Documento'}
                    </span>
                    <Download className="h-4 w-4 flex-shrink-0 opacity-60" />
                </a>
            );
        case 'sticker':
            return (
                <img
                    src={media_url}
                    alt="Sticker"
                    className="h-32 w-32"
                />
            );
        default:
            return (
                <div className={`flex items-center gap-2 py-1 ${textColor}`}>
                    <FileText className="h-4 w-4" />
                    <span className="text-xs italic">[{media_type}]</span>
                </div>
            );
    }
}

function MessageBubble({ message }: { message: Message }) {
    const isOut = message.direction === 'out';
    const time  = new Date(message.created_at).toLocaleTimeString('es-CO', {
        hour: '2-digit',
        minute: '2-digit',
    });

    return (
        <div className={`flex ${isOut ? 'justify-end' : 'justify-start'}`}>
            <div
                className={`max-w-[70%] rounded-2xl px-4 py-2 shadow-sm ${
                    isOut
                        ? 'rounded-br-sm bg-brand-500 text-white'
                        : 'rounded-bl-sm bg-white text-gray-900'
                }`}
            >
                <MediaContent message={message} isOut={isOut} />
                {message.body && <p className="text-sm leading-relaxed">{message.body}</p>}
                {!message.body && !message.media_type && (
                    <p className="text-xs italic opacity-70">[Mensaje vacío]</p>
                )}
                <div className={`mt-1 flex items-center gap-1 ${isOut ? 'justify-end' : 'justify-start'}`}>
                    <span className={`text-[10px] ${isOut ? 'text-brand-100' : 'text-gray-400'}`}>{time}</span>
                    {isOut && <StatusIcon status={message.status} />}
                </div>
            </div>
        </div>
    );
}

export default function MessageThread({ conversationId, messages, onMessageSent }: Props) {
    const [body, setBody]       = useState('');
    const [sending, setSending] = useState(false);
    const bottomRef             = useRef<HTMLDivElement>(null);

    useEffect(() => {
        bottomRef.current?.scrollIntoView({ behavior: 'smooth' });
    }, [messages]);

    async function sendMessage() {
        const text = body.trim();
        if (!text || sending) return;

        setSending(true);
        setBody('');
        try {
            const res = await axios.post<{ data: Message }>(
                `/api/v1/conversations/${conversationId}/messages`,
                { body: text },
            );
            onMessageSent(res.data.data);
        } catch {
            // Restore body on error
            setBody(text);
        } finally {
            setSending(false);
        }
    }

    function handleKeyDown(e: React.KeyboardEvent<HTMLTextAreaElement>) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendMessage();
        }
    }

    return (
        <div className="flex flex-1 flex-col overflow-hidden">
            {/* Messages */}
            <div className="flex-1 overflow-y-auto bg-gray-50 px-4 py-4">
                <div className="space-y-2">
                    {messages.map((msg) => (
                        <MessageBubble key={msg.id} message={msg} />
                    ))}
                    <div ref={bottomRef} />
                </div>
            </div>

            {/* Composer */}
            <div className="border-t border-gray-200 bg-white px-4 py-3">
                <div className="flex items-end gap-2">
                    <textarea
                        value={body}
                        onChange={(e) => setBody(e.target.value)}
                        onKeyDown={handleKeyDown}
                        placeholder="Escribe un mensaje… (Enter para enviar)"
                        rows={1}
                        className="flex-1 resize-none rounded-xl border border-gray-200 px-4 py-2.5 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500"
                        style={{ maxHeight: '120px' }}
                    />
                    <button
                        onClick={sendMessage}
                        disabled={!body.trim() || sending}
                        className="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-brand-600 text-white hover:bg-brand-700 disabled:opacity-40"
                    >
                        {sending ? (
                            <Loader2 className="h-4 w-4 animate-spin" />
                        ) : (
                            <Send className="h-4 w-4" />
                        )}
                    </button>
                </div>
            </div>
        </div>
    );
}

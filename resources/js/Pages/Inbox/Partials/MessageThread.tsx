import data from '@emoji-mart/data';
import Picker from '@emoji-mart/react';
import { Message, MessageStatus, QuickReply } from '@/types';
import axios from 'axios';
import { format } from 'date-fns';
import { es } from 'date-fns/locale';
import {
    Check,
    CheckCheck,
    Download,
    FileText,
    Loader2,
    Mic,
    Paperclip,
    Send,
    Smile,
    X,
    Zap,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

interface Props {
    conversationId: string;
    messages: Message[];
    nextCursor: string | null;
    onMessageSent: (message: Message) => void;
    onLoadOlderMessages: (messages: Message[], nextCursor: string | null) => void;
}

// ─── Status icon ──────────────────────────────────────────────────────────────

function StatusIcon({ status }: { status: MessageStatus }) {
    if (status === 'read') {
        return (
            <CheckCheck
                strokeWidth={2.5}
                className="h-4 w-4 text-cyan-200 drop-shadow-[0_0_2px_rgba(255,255,255,0.45)]"
            />
        );
    }

    if (status === 'delivered') {
        return (
            <CheckCheck
                strokeWidth={2.5}
                className="h-4 w-4 text-white/80 drop-shadow-[0_0_1px_rgba(255,255,255,0.25)]"
            />
        );
    }

    if (status === 'sent') {
        return (
            <Check
                strokeWidth={2.5}
                className="h-4 w-4 text-white/75 drop-shadow-[0_0_1px_rgba(255,255,255,0.2)]"
            />
        );
    }

    if (status === 'failed') return <span className="text-xs font-semibold text-red-200">!</span>;

    return <Loader2 className="h-4 w-4 animate-spin text-white/70" />;
}

// ─── Media content ────────────────────────────────────────────────────────────

function MediaContent({ message, isOut }: { message: Message; isOut: boolean }) {
    const { media_type, media_url, media_filename } = message;
    if (!media_type) return null;
    const textColor = isOut ? 'text-ari-100' : 'text-gray-500';

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
                    className="max-h-64 max-w-full cursor-pointer rounded-lg"
                    onClick={() => window.open(media_url, '_blank')}
                />
            );
        case 'video':
            return <video src={media_url} controls className="max-h-64 max-w-full rounded-lg" preload="metadata" />;
        case 'audio':
            return (
                <div className="flex items-center gap-2 py-1">
                    <Mic className={`h-4 w-4 flex-shrari-0 ${isOut ? 'text-ari-100' : 'text-ari-600'}`} />
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
                        isOut ? 'border-ari-400 hover:bg-ari-600' : 'border-gray-200 hover:bg-gray-50'
                    }`}
                >
                    <FileText className="h-5 w-5 flex-shrari-0" />
                    <span className="truncate text-xs font-medium">{media_filename ?? 'Documento'}</span>
                    <Download className="h-4 w-4 flex-shrari-0 opacity-60" />
                </a>
            );
        case 'sticker':
            return <img src={media_url} alt="Sticker" className="h-32 w-32" />;
        default:
            return (
                <div className={`flex items-center gap-2 py-1 ${textColor}`}>
                    <FileText className="h-4 w-4" />
                    <span className="text-xs italic">[{media_type}]</span>
                </div>
            );
    }
}

// ─── Message bubble ───────────────────────────────────────────────────────────

function MessageBubble({ message }: { message: Message }) {
    const isOut = message.direction === 'out';
    const timestamp = format(new Date(message.created_at), 'd MMM, h:mm a', { locale: es });

    return (
        <div className={`flex ${isOut ? 'justify-end' : 'justify-start'}`}>
            <div
                className={`max-w-[70%] rounded-2xl px-4 py-2 shadow-sm ${
                    isOut ? 'rounded-br-sm bg-ari-500 text-white' : 'rounded-bl-sm bg-white text-gray-900'
                }`}
            >
                <MediaContent message={message} isOut={isOut} />
                {message.body && <p className="text-sm leading-relaxed">{message.body}</p>}
                {!message.body && !message.media_type && (
                    <p className="text-xs italic opacity-70">[Mensaje vacío]</p>
                )}
                <div className={`mt-1 flex items-center gap-1 ${isOut ? 'justify-end' : 'justify-start'}`}>
                    <span className={`text-[10px] ${isOut ? 'text-ari-100' : 'text-gray-400'}`}>{timestamp}</span>
                    {isOut && <StatusIcon status={message.status} />}
                </div>
            </div>
        </div>
    );
}

// ─── Quick reply picker ───────────────────────────────────────────────────────

interface QuickReplyPickerProps {
    query: string;
    quickReplies: QuickReply[];
    onSelect: (qr: QuickReply) => void;
    onClose: () => void;
}

function QuickReplyPicker({ query, quickReplies, onSelect, onClose }: QuickReplyPickerProps) {
    const filtered = quickReplies.filter(
        (qr) =>
            qr.shortcut.toLowerCase().includes(query.toLowerCase()) ||
            qr.title.toLowerCase().includes(query.toLowerCase()),
    );

    if (filtered.length === 0) return null;

    return (
        <div className="absolute bottom-full left-0 right-0 z-10 mb-2 max-h-60 overflow-y-auto rounded-xl border border-gray-200 bg-white shadow-lg">
            <div className="flex items-center justify-between border-b border-gray-100 px-3 py-2">
                <span className="flex items-center gap-1.5 text-xs font-medium text-gray-500">
                    <Zap className="h-3.5 w-3.5 text-ari-500" />
                    Respuestas rápidas
                </span>
                <button onClick={onClose} className="text-gray-400 hover:text-gray-600">
                    <X className="h-3.5 w-3.5" />
                </button>
            </div>
            {filtered.map((qr) => (
                <button
                    key={qr.id}
                    onClick={() => onSelect(qr)}
                    className="flex w-full flex-col px-3 py-2.5 text-left hover:bg-gray-50"
                >
                    <span className="text-xs font-semibold text-ari-600">/{qr.shortcut}</span>
                    <span className="text-sm text-gray-800">{qr.title}</span>
                    <span className="mt-0.5 truncate text-xs text-gray-400">{qr.body}</span>
                </button>
            ))}
        </div>
    );
}

// ─── Media preview ────────────────────────────────────────────────────────────

function MediaPreview({ file, onRemove }: { file: File; onRemove: () => void }) {
    const url      = URL.createObjectURL(file);
    const isImage  = file.type.startsWith('image/');
    const isVideo  = file.type.startsWith('video/');

    return (
        <div className="relative mb-2 inline-block">
            {isImage ? (
                <img src={url} alt="preview" className="h-20 w-20 rounded-lg object-cover" />
            ) : isVideo ? (
                <video src={url} className="h-20 w-20 rounded-lg object-cover" />
            ) : (
                <div className="flex h-20 w-20 items-center justify-center rounded-lg bg-gray-100">
                    <FileText className="h-8 w-8 text-gray-400" />
                </div>
            )}
            <button
                onClick={onRemove}
                className="absolute -right-1.5 -top-1.5 flex h-5 w-5 items-center justify-center rounded-full bg-gray-700 text-white hover:bg-gray-900"
            >
                <X className="h-3 w-3" />
            </button>
            <p className="mt-0.5 max-w-[80px] truncate text-center text-[10px] text-gray-500">{file.name}</p>
        </div>
    );
}

// ─── Main component ───────────────────────────────────────────────────────────

export default function MessageThread({
    conversationId,
    messages,
    nextCursor,
    onMessageSent,
    onLoadOlderMessages,
}: Props) {
    const [body, setBody]               = useState('');
    const [sending, setSending]         = useState(false);
    const [mediaFile, setMediaFile]     = useState<File | null>(null);
    const [loadingOlder, setLoadingOlder] = useState(false);
    const [quickReplies, setQuickReplies] = useState<QuickReply[] | null>(null);
    const [selectedQuickReply, setSelectedQuickReply] = useState<QuickReply | null>(null);
    const [showQR, setShowQR]           = useState(false);
    const [qrQuery, setQrQuery]         = useState('');
    const [showEmojiPicker, setShowEmojiPicker] = useState(false);

    const bottomRef      = useRef<HTMLDivElement>(null);
    const topSentinelRef = useRef<HTMLDivElement>(null);
    const fileInputRef   = useRef<HTMLInputElement>(null);
    const listRef        = useRef<HTMLDivElement>(null);
    const textareaRef    = useRef<HTMLTextAreaElement>(null);
    const emojiPickerRef = useRef<HTMLDivElement>(null);

    // Close emoji picker when clicking outside
    useEffect(() => {
        function handleClick(e: MouseEvent) {
            if (emojiPickerRef.current && !emojiPickerRef.current.contains(e.target as Node)) {
                setShowEmojiPicker(false);
            }
        }
        if (showEmojiPicker) {
            document.addEventListener('mousedown', handleClick);
            return () => document.removeEventListener('mousedown', handleClick);
        }
    }, [showEmojiPicker]);

    // Scroll to bottom and focus textarea when conversation first loads
    useEffect(() => {
        bottomRef.current?.scrollIntoView({ behavior: 'instant' });
        textareaRef.current?.focus();
        setSelectedQuickReply(null);
        setShowEmojiPicker(false);
    }, [conversationId]);

    // Scroll to bottom on new message (only if user is near bottom)
    const prevMsgCount = useRef(messages.length);
    useEffect(() => {
        if (messages.length > prevMsgCount.current) {
            const container = listRef.current;
            if (container) {
                const distanceFromBottom = container.scrollHeight - container.scrollTop - container.clientHeight;
                if (distanceFromBottom < 200) {
                    bottomRef.current?.scrollIntoView({ behavior: 'smooth' });
                }
            }
        }
        prevMsgCount.current = messages.length;
    }, [messages.length]);

    // IntersectionObserver for infinite scroll (load older messages)
    useEffect(() => {
        if (!nextCursor || !topSentinelRef.current) return;

        const observer = new IntersectionObserver(
            (entries) => {
                if (entries[0].isIntersecting && !loadingOlder && nextCursor) {
                    loadOlderMessages();
                }
            },
            { threshold: 0.1 },
        );

        observer.observe(topSentinelRef.current);
        return () => observer.disconnect();
    }, [nextCursor, loadingOlder]);

    // Load quick replies on demand
    async function fetchQuickReplies() {
        if (quickReplies !== null) return;
        const res = await axios.get<{ data: QuickReply[] }>('/api/v1/quick-replies');
        setQuickReplies(res.data.data);
    }

    async function loadOlderMessages() {
        if (loadingOlder || !nextCursor) return;
        setLoadingOlder(true);

        const container = listRef.current;
        const prevScrollHeight = container?.scrollHeight ?? 0;

        try {
            const res = await axios.get<{ data: Message[]; links: { next: string | null } }>(
                `/api/v1/conversations/${conversationId}/messages`,
                { params: { cursor: nextCursor } },
            );
            const olderMessages = [...res.data.data].reverse();
            const newNextCursor = res.data.links.next
                ? new URL(res.data.links.next).searchParams.get('cursor')
                : null;

            onLoadOlderMessages(olderMessages, newNextCursor);

            requestAnimationFrame(() => {
                if (container) {
                    container.scrollTop = container.scrollHeight - prevScrollHeight;
                }
            });
        } finally {
            setLoadingOlder(false);
        }
    }

    function handleBodyChange(e: React.ChangeEvent<HTMLTextAreaElement>) {
        const value = e.target.value;
        setBody(value);
        if (selectedQuickReply && value !== selectedQuickReply.body) {
            setSelectedQuickReply(null);
        }

        if (value.startsWith('/')) {
            const query = value.slice(1);
            setQrQuery(query);
            setShowQR(true);
            fetchQuickReplies();
        } else {
            setShowQR(false);
        }
    }

    function handleQuickReplySelect(qr: QuickReply) {
        setBody(qr.body);
        setSelectedQuickReply(qr);
        setShowQR(false);
        setQrQuery('');
    }

    function handleEmojiSelect(emoji: { native: string }) {
        const textarea = textareaRef.current;
        if (!textarea) {
            setBody((prev) => prev + emoji.native);
            return;
        }
        const start = textarea.selectionStart ?? body.length;
        const end   = textarea.selectionEnd ?? body.length;
        const newBody = body.slice(0, start) + emoji.native + body.slice(end);
        setBody(newBody);
        // Restore cursor after emoji
        requestAnimationFrame(() => {
            const pos = start + emoji.native.length;
            textarea.setSelectionRange(pos, pos);
            textarea.focus();
        });
    }

    function handleFileChange(e: React.ChangeEvent<HTMLInputElement>) {
        const file = e.target.files?.[0];
        if (file) setMediaFile(file);
        e.target.value = '';
    }

    async function sendMessage() {
        const text = body.trim();
        if ((!text && !mediaFile) || sending) return;

        setSending(true);
        const savedBody = body;
        const savedFile = mediaFile;
        const savedQuickReply = selectedQuickReply;
        setBody('');
        setMediaFile(null);
        setSelectedQuickReply(null);
        setShowEmojiPicker(false);

        try {
            let res: { data: { data: Message } };
            let quickReplyToSend: QuickReply | null = savedQuickReply;

            if (!quickReplyToSend && !savedFile && text.startsWith('/')) {
                const shortcut = text.slice(1).trim().toLowerCase();
                const isExactSlashCommand = shortcut !== '' && !shortcut.includes(' ');

                if (isExactSlashCommand) {
                    let pool = quickReplies;
                    if (pool === null) {
                        const qrRes = await axios.get<{ data: QuickReply[] }>('/api/v1/quick-replies');
                        pool = qrRes.data.data;
                        setQuickReplies(pool);
                    }

                    quickReplyToSend = pool.find((qr) => qr.shortcut.toLowerCase() === shortcut) ?? null;
                }
            }

            if (quickReplyToSend && !savedFile) {
                res = await axios.post(
                    `/api/v1/conversations/${conversationId}/messages/quick-reply/${quickReplyToSend.id}`,
                );
            } else if (savedFile) {
                const form = new FormData();
                form.append('media', savedFile);
                if (text) form.append('body', text);
                res = await axios.post(`/api/v1/conversations/${conversationId}/messages/media`, form, {
                    headers: { 'Content-Type': 'multipart/form-data' },
                });
            } else {
                res = await axios.post(`/api/v1/conversations/${conversationId}/messages`, { body: text });
            }

            onMessageSent(res.data.data);
        } catch {
            setBody(savedBody);
            setMediaFile(savedFile);
            setSelectedQuickReply(savedQuickReply);
        } finally {
            setSending(false);
        }
    }

    function handleKeyDown(e: React.KeyboardEvent<HTMLTextAreaElement>) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendMessage();
        }
        if (e.key === 'Escape') {
            if (showQR) setShowQR(false);
            if (showEmojiPicker) setShowEmojiPicker(false);
        }
    }

    return (
        <div className="flex flex-1 flex-col overflow-hidden">
            {/* Messages list */}
            <div ref={listRef} className="flex-1 overflow-y-auto bg-gray-50 px-4 py-4">
                {/* Top sentinel + loading indicator */}
                <div ref={topSentinelRef} className="flex justify-center py-2">
                    {loadingOlder && (
                        <Loader2 className="h-5 w-5 animate-spin text-gray-400" />
                    )}
                    {!loadingOlder && nextCursor && (
                        <button
                            onClick={loadOlderMessages}
                            className="text-xs text-ari-600 hover:underline"
                        >
                            Cargar mensajes anteriores
                        </button>
                    )}
                </div>

                <div className="space-y-2">
                    {messages.map((msg) => (
                        <MessageBubble key={msg.id} message={msg} />
                    ))}
                    <div ref={bottomRef} />
                </div>
            </div>

            {/* Composer */}
            <div className="border-t border-gray-200 bg-white px-4 py-3">
                {/* Media preview */}
                {mediaFile && (
                    <MediaPreview file={mediaFile} onRemove={() => setMediaFile(null)} />
                )}

                {/* Quick replies picker + emoji picker (above composer) */}
                <div className="relative">
                    {showQR && quickReplies !== null && (
                        <QuickReplyPicker
                            query={qrQuery}
                            quickReplies={quickReplies}
                            onSelect={handleQuickReplySelect}
                            onClose={() => setShowQR(false)}
                        />
                    )}

                    {showEmojiPicker && (
                        <div
                            ref={emojiPickerRef}
                            className="absolute bottom-full left-0 z-20 mb-2"
                        >
                            <Picker
                                data={data}
                                onEmojiSelect={handleEmojiSelect}
                                locale="es"
                                theme="light"
                                previewPosition="none"
                                skinTonePosition="none"
                            />
                        </div>
                    )}

                    <div className="flex items-end gap-2">
                        {/* Media upload button */}
                        <button
                            onClick={() => fileInputRef.current?.click()}
                            className="flex h-10 w-10 flex-shrari-0 items-center justify-center rounded-full text-gray-400 hover:bg-gray-100 hover:text-gray-600"
                            title="Adjuntar archivo"
                        >
                            <Paperclip className="h-5 w-5" />
                        </button>
                        <input
                            ref={fileInputRef}
                            type="file"
                            className="hidden"
                            accept="image/*,video/*,audio/*,.pdf,.doc,.docx,.xls,.xlsx,.zip"
                            onChange={handleFileChange}
                        />

                        {/* Emoji button */}
                        <button
                            onClick={() => setShowEmojiPicker((v) => !v)}
                            className={`flex h-10 w-10 flex-shrari-0 items-center justify-center rounded-full text-gray-400 hover:bg-gray-100 hover:text-gray-600 ${showEmojiPicker ? 'bg-gray-100 text-ari-500' : ''}`}
                            title="Emojis"
                        >
                            <Smile className="h-5 w-5" />
                        </button>

                        {/* Text input */}
                        <textarea
                            ref={textareaRef}
                            value={body}
                            onChange={handleBodyChange}
                            onKeyDown={handleKeyDown}
                            placeholder="Escribe un mensaje… (/ para respuestas rápidas)"
                            rows={1}
                            className="flex-1 resize-none rounded-xl border border-gray-200 px-4 py-2.5 text-sm focus:border-ari-500 focus:outline-none focus:ring-1 focus:ring-ari-500"
                            style={{ maxHeight: '120px' }}
                        />

                        {/* Send button */}
                        <button
                            onClick={sendMessage}
                            disabled={(!body.trim() && !mediaFile) || sending}
                            className="flex h-10 w-10 flex-shrari-0 items-center justify-center rounded-full bg-ari-600 text-white hover:bg-ari-700 disabled:opacity-40"
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
        </div>
    );
}

import AppLayout from '@/Layouts/AppLayout';
import { QuickReply } from '@/types';
import { Head } from '@inertiajs/react';
import axios from 'axios';
import { Loader2, MessageSquareText, Pencil, Plus, Search, Trash2, X, Zap } from 'lucide-react';
import { useEffect, useState } from 'react';

interface ValidationErrors {
    shortcut?: string;
    title?: string;
    body?: string;
    category?: string;
}

interface QuickReplyModalProps {
    quickReply: QuickReply | null;
    onClose: () => void;
    onSaved: () => void;
}

function normalizeShortcut(value: string): string {
    return value.trim().replace(/^\/+/, '').toLowerCase();
}

function QuickReplyModal({ quickReply, onClose, onSaved }: QuickReplyModalProps) {
    const isEdit = quickReply !== null;
    const [shortcut, setShortcut] = useState(quickReply?.shortcut ?? '');
    const [title, setTitle] = useState(quickReply?.title ?? '');
    const [body, setBody] = useState(quickReply?.body ?? '');
    const [category, setCategory] = useState(quickReply?.category ?? 'general');
    const [saving, setSaving] = useState(false);
    const [errors, setErrors] = useState<ValidationErrors>({});

    async function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        setSaving(true);
        setErrors({});

        try {
            const payload = {
                shortcut: normalizeShortcut(shortcut),
                title: title.trim(),
                body: body.trim(),
                category: category.trim() || 'general',
            };

            if (isEdit) {
                await axios.put(`/api/v1/quick-replies/${quickReply.id}`, payload);
            } else {
                await axios.post('/api/v1/quick-replies', payload);
            }

            onSaved();
        } catch (err: unknown) {
            if (axios.isAxiosError(err) && err.response?.data?.errors) {
                const rawErrors = err.response.data.errors as Record<string, string[] | string>;
                const nextErrors: ValidationErrors = {};
                for (const [key, value] of Object.entries(rawErrors)) {
                    nextErrors[key as keyof ValidationErrors] = Array.isArray(value) ? value[0] : value;
                }
                setErrors(nextErrors);
            }
        } finally {
            setSaving(false);
        }
    }

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div className="w-full max-w-xl rounded-xl bg-white shadow-2xl">
                <div className="flex items-center justify-between border-b border-gray-200 px-6 py-4">
                    <h2 className="text-lg font-semibold text-gray-900">
                        {isEdit ? 'Editar respuesta rápida' : 'Nueva respuesta rápida'}
                    </h2>
                    <button onClick={onClose} className="text-gray-400 hover:text-gray-600">
                        <X className="h-5 w-5" />
                    </button>
                </div>

                <form onSubmit={handleSubmit} className="space-y-4 px-6 py-5">
                    <div>
                        <label className="mb-1 block text-sm font-medium text-gray-700">
                            Atajo <span className="text-gray-400">(sin /)</span>
                        </label>
                        <input
                            value={shortcut}
                            onChange={(e) => setShortcut(e.target.value)}
                            placeholder="ej: horario"
                            className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500"
                            required
                        />
                        {errors.shortcut && <p className="mt-1 text-xs text-red-600">{errors.shortcut}</p>}
                    </div>

                    <div>
                        <label className="mb-1 block text-sm font-medium text-gray-700">Título</label>
                        <input
                            value={title}
                            onChange={(e) => setTitle(e.target.value)}
                            placeholder="Horario de atención"
                            className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500"
                            required
                        />
                        {errors.title && <p className="mt-1 text-xs text-red-600">{errors.title}</p>}
                    </div>

                    <div>
                        <label className="mb-1 block text-sm font-medium text-gray-700">
                            Mensaje
                        </label>
                        <textarea
                            value={body}
                            onChange={(e) => setBody(e.target.value)}
                            placeholder="Nuestro horario es de lunes a viernes de 8am a 6pm."
                            rows={5}
                            className="w-full resize-y rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500"
                            required
                        />
                        <p className="mt-1 text-xs text-gray-500">
                            Variables disponibles: {'{{name}}'}, {'{{phone}}'}, {'{{company}}'}
                        </p>
                        {errors.body && <p className="mt-1 text-xs text-red-600">{errors.body}</p>}
                    </div>

                    <div>
                        <label className="mb-1 block text-sm font-medium text-gray-700">Categoría</label>
                        <input
                            value={category}
                            onChange={(e) => setCategory(e.target.value)}
                            placeholder="general"
                            className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500"
                        />
                        {errors.category && <p className="mt-1 text-xs text-red-600">{errors.category}</p>}
                    </div>

                    <div className="flex justify-end gap-2 border-t border-gray-200 pt-4">
                        <button
                            type="button"
                            onClick={onClose}
                            className="rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50"
                        >
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            disabled={saving}
                            className="inline-flex items-center gap-2 rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700 disabled:opacity-60"
                        >
                            {saving && <Loader2 className="h-4 w-4 animate-spin" />}
                            {isEdit ? 'Guardar cambios' : 'Crear respuesta'}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}

export default function QuickRepliesPage() {
    const [quickReplies, setQuickReplies] = useState<QuickReply[]>([]);
    const [search, setSearch] = useState('');
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [showModal, setShowModal] = useState(false);
    const [editingQuickReply, setEditingQuickReply] = useState<QuickReply | null>(null);
    const [deletingId, setDeletingId] = useState<string | null>(null);

    async function fetchQuickReplies(searchValue = '') {
        setLoading(true);
        setError(null);

        try {
            const params = searchValue.trim() !== '' ? { search: searchValue.trim() } : undefined;
            const res = await axios.get<{ data: QuickReply[] }>('/api/v1/quick-replies', { params });
            setQuickReplies(res.data.data);
        } catch (err: unknown) {
            const msg = axios.isAxiosError(err)
                ? (err.response?.data?.message ?? 'No se pudieron cargar las respuestas rápidas.')
                : 'No se pudieron cargar las respuestas rápidas.';
            setError(msg);
        } finally {
            setLoading(false);
        }
    }

    useEffect(() => {
        const timer = setTimeout(() => {
            void fetchQuickReplies(search);
        }, 250);

        return () => clearTimeout(timer);
    }, [search]);

    function openCreateModal() {
        setEditingQuickReply(null);
        setShowModal(true);
    }

    function openEditModal(quickReply: QuickReply) {
        setEditingQuickReply(quickReply);
        setShowModal(true);
    }

    async function handleDelete(quickReply: QuickReply) {
        const shouldDelete = window.confirm(`¿Eliminar la respuesta /${quickReply.shortcut}?`);
        if (!shouldDelete) return;

        setDeletingId(quickReply.id);
        try {
            await axios.delete(`/api/v1/quick-replies/${quickReply.id}`);
            setQuickReplies((prev) => prev.filter((item) => item.id !== quickReply.id));
        } catch (err: unknown) {
            const msg = axios.isAxiosError(err)
                ? (err.response?.data?.message ?? 'No se pudo eliminar la respuesta rápida.')
                : 'No se pudo eliminar la respuesta rápida.';
            setError(msg);
        } finally {
            setDeletingId(null);
        }
    }

    return (
        <AppLayout title="Respuestas rápidas">
            <Head title="Respuestas rápidas" />

            <div className="mx-auto max-w-5xl space-y-5 p-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold text-gray-900">Respuestas rápidas</h1>
                        <p className="mt-1 text-sm text-gray-500">
                            Crea plantillas y úsalas desde el inbox escribiendo <span className="font-medium text-gray-700">/</span>.
                        </p>
                    </div>
                    <button
                        onClick={openCreateModal}
                        className="inline-flex items-center gap-2 rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700"
                    >
                        <Plus className="h-4 w-4" />
                        Nueva respuesta
                    </button>
                </div>

                <div className="rounded-xl border border-gray-200 bg-white p-4">
                    <div className="relative">
                        <Search className="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-gray-400" />
                        <input
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Buscar por atajo, título o contenido..."
                            className="w-full rounded-lg border border-gray-300 py-2 pl-9 pr-3 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500"
                        />
                    </div>
                </div>

                {error && (
                    <div className="rounded-lg border border-red-100 bg-red-50 px-4 py-3 text-sm text-red-700">
                        {error}
                    </div>
                )}

                <div className="overflow-hidden rounded-xl border border-gray-200 bg-white">
                    {loading ? (
                        <div className="flex items-center justify-center gap-2 px-4 py-12 text-sm text-gray-500">
                            <Loader2 className="h-4 w-4 animate-spin" />
                            Cargando respuestas rápidas...
                        </div>
                    ) : quickReplies.length === 0 ? (
                        <div className="flex flex-col items-center justify-center gap-2 px-4 py-12 text-center text-sm text-gray-500">
                            <MessageSquareText className="h-8 w-8 text-gray-300" />
                            <p>No hay respuestas rápidas para este filtro.</p>
                        </div>
                    ) : (
                        <div className="divide-y divide-gray-100">
                            {quickReplies.map((quickReply) => (
                                <article key={quickReply.id} className="flex items-start justify-between gap-3 px-4 py-4">
                                    <div className="min-w-0 flex-1">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <span className="inline-flex items-center gap-1 rounded-md bg-brand-50 px-2 py-1 text-xs font-semibold text-brand-700">
                                                <Zap className="h-3 w-3" />
                                                /{quickReply.shortcut}
                                            </span>
                                            <h2 className="text-sm font-semibold text-gray-900">{quickReply.title}</h2>
                                            <span className="rounded-md bg-gray-100 px-2 py-1 text-xs text-gray-600">
                                                {quickReply.category || 'general'}
                                            </span>
                                            <span className="text-xs text-gray-400">
                                                Usos: {quickReply.usage_count}
                                            </span>
                                        </div>
                                        <p className="mt-2 truncate text-sm text-gray-600">
                                            {quickReply.body}
                                        </p>
                                    </div>

                                    <div className="flex items-center gap-1">
                                        <button
                                            onClick={() => openEditModal(quickReply)}
                                            className="rounded-md p-2 text-gray-500 hover:bg-gray-100 hover:text-gray-700"
                                            title="Editar"
                                        >
                                            <Pencil className="h-4 w-4" />
                                        </button>
                                        <button
                                            onClick={() => handleDelete(quickReply)}
                                            disabled={deletingId === quickReply.id}
                                            className="rounded-md p-2 text-red-500 hover:bg-red-50 hover:text-red-600 disabled:opacity-50"
                                            title="Eliminar"
                                        >
                                            {deletingId === quickReply.id ? (
                                                <Loader2 className="h-4 w-4 animate-spin" />
                                            ) : (
                                                <Trash2 className="h-4 w-4" />
                                            )}
                                        </button>
                                    </div>
                                </article>
                            ))}
                        </div>
                    )}
                </div>
            </div>

            {showModal && (
                <QuickReplyModal
                    quickReply={editingQuickReply}
                    onClose={() => setShowModal(false)}
                    onSaved={() => {
                        setShowModal(false);
                        void fetchQuickReplies(search);
                    }}
                />
            )}
        </AppLayout>
    );
}

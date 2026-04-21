import AppLayout from '@/Layouts/AppLayout';
import { Tag } from '@/types';
import { Head, Link } from '@inertiajs/react';
import axios from 'axios';
import { ArrowLeft, ChevronRight, Loader2, Pencil, Plus, Tag as TagIcon, Trash2, X } from 'lucide-react';
import { useEffect, useState } from 'react';

// ─── Color palette ────────────────────────────────────────────────────────────

const COLORS = [
    { hex: '#6366f1', label: 'Índigo'    },
    { hex: '#8b5cf6', label: 'Violeta'   },
    { hex: '#ec4899', label: 'Rosa'      },
    { hex: '#ef4444', label: 'Rojo'      },
    { hex: '#f97316', label: 'Naranja'   },
    { hex: '#eab308', label: 'Amarillo'  },
    { hex: '#22c55e', label: 'Verde'     },
    { hex: '#14b8a6', label: 'Teal'      },
    { hex: '#3b82f6', label: 'Azul'      },
    { hex: '#64748b', label: 'Gris'      },
];

// ─── Modal ────────────────────────────────────────────────────────────────────

interface TagModalProps {
    tag: Tag | null;
    onClose: () => void;
    onSaved: (tag: Tag) => void;
}

function TagModal({ tag, onClose, onSaved }: TagModalProps) {
    const isEdit = tag !== null;
    const [name, setName]   = useState(tag?.name ?? '');
    const [color, setColor] = useState(tag?.color ?? COLORS[0].hex);
    const [excludeFromMetrics, setExcludeFromMetrics] = useState(tag?.exclude_from_metrics ?? false);
    const [saving, setSaving] = useState(false);
    const [nameError, setNameError] = useState('');

    async function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        if (!name.trim()) { setNameError('El nombre es obligatorio.'); return; }
        setSaving(true);
        setNameError('');

        try {
            const payload = { name: name.trim(), color, exclude_from_metrics: excludeFromMetrics };
            const res = isEdit
                ? await axios.patch<{ data: Tag }>(`/api/v1/tags/${tag.id}`, payload)
                : await axios.post<{ data: Tag }>('/api/v1/tags', payload);
            onSaved(res.data.data);
        } catch (err: unknown) {
            if (axios.isAxiosError(err)) {
                const msg = err.response?.data?.errors?.name?.[0]
                    ?? err.response?.data?.message
                    ?? 'Error al guardar.';
                setNameError(msg);
            }
        } finally {
            setSaving(false);
        }
    }

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div className="flex w-full max-w-md flex-col rounded-xl bg-white shadow-2xl">
                {/* Header */}
                <div className="flex items-center justify-between border-b border-gray-200 px-6 py-4">
                    <h2 className="text-lg font-semibold text-gray-900">
                        {isEdit ? 'Editar etiqueta' : 'Nueva etiqueta'}
                    </h2>
                    <button
                        onClick={onClose}
                        className="flex h-9 w-9 items-center justify-center rounded-lg text-gray-400 hover:bg-gray-100 hover:text-gray-600"
                    >
                        <X className="h-5 w-5" />
                    </button>
                </div>

                {/* Form */}
                <form onSubmit={handleSubmit} className="space-y-5 px-6 py-5">
                    {/* Name */}
                    <div>
                        <label className="mb-1.5 block text-sm font-medium text-gray-700">
                            Nombre <span className="text-red-500">*</span>
                        </label>
                        <input
                            autoFocus
                            value={name}
                            onChange={(e) => setName(e.target.value)}
                            placeholder="ej. Proveedor"
                            className={`w-full rounded-lg border px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ari-500 ${
                                nameError ? 'border-red-400' : 'border-gray-200'
                            }`}
                        />
                        {nameError && <p className="mt-1 text-xs text-red-500">{nameError}</p>}
                    </div>

                    {/* Color */}
                    <div>
                        <label className="mb-1.5 block text-sm font-medium text-gray-700">Color</label>
                        <div className="flex flex-wrap gap-2">
                            {COLORS.map((c) => (
                                <button
                                    key={c.hex}
                                    type="button"
                                    title={c.label}
                                    onClick={() => setColor(c.hex)}
                                    className={`h-7 w-7 rounded-full transition-transform hover:scale-110 ${
                                        color === c.hex ? 'ring-2 ring-offset-2 ring-gray-500 scale-110' : ''
                                    }`}
                                    style={{ backgroundColor: c.hex }}
                                />
                            ))}
                        </div>
                        {/* Preview */}
                        <div className="mt-3">
                            <span
                                className="inline-flex items-center rounded-full px-3 py-1 text-sm font-medium text-white"
                                style={{ backgroundColor: color }}
                            >
                                {name.trim() || 'Vista previa'}
                            </span>
                        </div>
                    </div>

                    {/* Exclude from metrics */}
                    <label className="flex cursor-pointer items-start gap-3 rounded-lg border border-gray-200 p-3 hover:bg-gray-50">
                        <input
                            type="checkbox"
                            checked={excludeFromMetrics}
                            onChange={(e) => setExcludeFromMetrics(e.target.checked)}
                            className="mt-0.5 h-4 w-4 rounded border-gray-300 text-ari-600 focus:ring-ari-500"
                        />
                        <div>
                            <p className="text-sm font-medium text-gray-900">Excluir de métricas</p>
                            <p className="text-xs text-gray-500">
                                Los contactos con esta etiqueta no se contabilizarán en el tiempo de respuesta (DT1) del dashboard.
                            </p>
                        </div>
                    </label>

                    {/* Actions */}
                    <div className="flex justify-end gap-2 border-t border-gray-100 pt-4">
                        <button
                            type="button"
                            onClick={onClose}
                            className="rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                        >
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            disabled={saving}
                            className="flex items-center gap-2 rounded-lg bg-ari-600 px-4 py-2 text-sm font-medium text-white hover:bg-ari-700 disabled:opacity-60"
                        >
                            {saving && <Loader2 className="h-4 w-4 animate-spin" />}
                            {isEdit ? 'Guardar cambios' : 'Crear etiqueta'}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}

// ─── Delete confirm ───────────────────────────────────────────────────────────

function DeleteConfirm({ tag, onClose, onDeleted }: { tag: Tag; onClose: () => void; onDeleted: (id: string) => void }) {
    const [deleting, setDeleting] = useState(false);

    async function handleDelete() {
        setDeleting(true);
        try {
            await axios.delete(`/api/v1/tags/${tag.id}`);
            onDeleted(tag.id);
        } finally {
            setDeleting(false);
        }
    }

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div className="w-full max-w-sm rounded-xl bg-white p-6 shadow-2xl">
                <h3 className="text-base font-semibold text-gray-900">¿Eliminar etiqueta?</h3>
                <p className="mt-2 text-sm text-gray-500">
                    La etiqueta <strong>"{tag.name}"</strong> se eliminará de todos los contactos que la tengan asignada.
                    Esta acción no se puede deshacer.
                </p>
                <div className="mt-5 flex justify-end gap-2">
                    <button
                        onClick={onClose}
                        className="rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                    >
                        Cancelar
                    </button>
                    <button
                        onClick={handleDelete}
                        disabled={deleting}
                        className="flex items-center gap-2 rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-60"
                    >
                        {deleting && <Loader2 className="h-4 w-4 animate-spin" />}
                        Eliminar
                    </button>
                </div>
            </div>
        </div>
    );
}

// ─── Page ─────────────────────────────────────────────────────────────────────

export default function TagsSettings() {
    const [tags, setTags]       = useState<Tag[]>([]);
    const [loading, setLoading] = useState(true);
    const [modal, setModal]     = useState<'create' | Tag | null>(null);
    const [deleting, setDeleting] = useState<Tag | null>(null);

    useEffect(() => {
        axios.get<{ data: Tag[] }>('/api/v1/tags')
            .then((r) => setTags(r.data.data))
            .finally(() => setLoading(false));
    }, []);

    function handleSaved(saved: Tag) {
        setTags((prev) => {
            const idx = prev.findIndex((t) => t.id === saved.id);
            return idx >= 0
                ? prev.map((t) => (t.id === saved.id ? saved : t))
                : [...prev, saved].sort((a, b) => a.name.localeCompare(b.name));
        });
        setModal(null);
    }

    function handleDeleted(id: string) {
        setTags((prev) => prev.filter((t) => t.id !== id));
        setDeleting(null);
    }

    return (
        <AppLayout title="Etiquetas">
            <Head title="Etiquetas" />

            {modal === 'create' && (
                <TagModal tag={null} onClose={() => setModal(null)} onSaved={handleSaved} />
            )}
            {modal && modal !== 'create' && (
                <TagModal tag={modal as Tag} onClose={() => setModal(null)} onSaved={handleSaved} />
            )}
            {deleting && (
                <DeleteConfirm tag={deleting} onClose={() => setDeleting(null)} onDeleted={handleDeleted} />
            )}

            <div className="space-y-6 p-6">
                {/* Breadcrumb */}
                <nav className="flex items-center gap-1.5 text-sm text-gray-500">
                    <Link href="/settings" className="hover:text-gray-700">Configuración</Link>
                    <ChevronRight className="h-4 w-4" />
                    <span className="font-medium text-gray-900">Etiquetas</span>
                </nav>

                {/* Header */}
                <div className="flex items-start justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold text-gray-900">Etiquetas</h1>
                        <p className="mt-1 text-sm text-gray-500">
                            Organiza tus contactos y controla qué grupos se excluyen del cálculo de DT1.
                        </p>
                    </div>
                    <button
                        onClick={() => setModal('create')}
                        className="flex shrink-0 items-center gap-2 rounded-lg bg-ari-600 px-4 py-2 text-sm font-medium text-white hover:bg-ari-700"
                    >
                        <Plus className="h-4 w-4" />
                        Nueva etiqueta
                    </button>
                </div>

                {/* Content */}
                {loading ? (
                    <div className="flex items-center justify-center py-20">
                        <Loader2 className="h-6 w-6 animate-spin text-gray-400" />
                    </div>
                ) : tags.length === 0 ? (
                    <div className="flex flex-col items-center justify-center rounded-xl border border-dashed border-gray-300 py-16 text-center">
                        <TagIcon className="h-10 w-10 text-gray-300" />
                        <p className="mt-3 text-sm font-medium text-gray-500">Sin etiquetas todavía</p>
                        <p className="mt-1 text-xs text-gray-400">Crea tu primera etiqueta para segmentar contactos.</p>
                        <button
                            onClick={() => setModal('create')}
                            className="mt-5 flex items-center gap-2 rounded-lg bg-ari-600 px-4 py-2 text-sm font-medium text-white hover:bg-ari-700"
                        >
                            <Plus className="h-4 w-4" />
                            Nueva etiqueta
                        </button>
                    </div>
                ) : (
                    <div className="overflow-hidden rounded-xl border border-gray-200 bg-white">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b border-gray-100 bg-gray-50 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                                    <th className="px-4 py-3">Etiqueta</th>
                                    <th className="px-4 py-3">Slug</th>
                                    <th className="px-4 py-3 text-center">Excluye DT1</th>
                                    <th className="px-4 py-3" />
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {tags.map((tag) => (
                                    <tr key={tag.id} className="group hover:bg-gray-50">
                                        {/* Tag chip */}
                                        <td className="px-4 py-3">
                                            <span
                                                className="inline-flex items-center rounded-full px-3 py-1 text-sm font-medium text-white"
                                                style={{ backgroundColor: tag.color }}
                                            >
                                                {tag.name}
                                            </span>
                                        </td>

                                        {/* Slug */}
                                        <td className="px-4 py-3 font-mono text-xs text-gray-400">
                                            {tag.slug}
                                        </td>

                                        {/* Exclude from metrics */}
                                        <td className="px-4 py-3 text-center">
                                            {tag.exclude_from_metrics ? (
                                                <span className="inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-700">
                                                    Excluido
                                                </span>
                                            ) : (
                                                <span className="text-gray-300">—</span>
                                            )}
                                        </td>

                                        {/* Actions */}
                                        <td className="px-4 py-3">
                                            <div className="flex items-center justify-end gap-1 opacity-0 transition-opacity group-hover:opacity-100">
                                                <button
                                                    onClick={() => setModal(tag)}
                                                    className="flex h-8 w-8 items-center justify-center rounded-lg text-gray-400 hover:bg-gray-100 hover:text-gray-600"
                                                    title="Editar"
                                                >
                                                    <Pencil className="h-4 w-4" />
                                                </button>
                                                <button
                                                    onClick={() => setDeleting(tag)}
                                                    className="flex h-8 w-8 items-center justify-center rounded-lg text-gray-400 hover:bg-red-50 hover:text-red-600"
                                                    title="Eliminar"
                                                >
                                                    <Trash2 className="h-4 w-4" />
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                {/* Info box */}
                <div className="rounded-lg border border-blue-100 bg-blue-50 p-4 text-sm text-blue-700">
                    <strong>¿Cómo funciona "Excluye DT1"?</strong>
                    <p className="mt-1 text-xs text-blue-600">
                        Cuando activas esta opción en una etiqueta (ej. "Proveedor"), las conversaciones con esos contactos
                        se omiten del cálculo de tiempo de primera respuesta en el dashboard. Así las métricas reflejan
                        solo la atención real al cliente.
                    </p>
                </div>
            </div>
        </AppLayout>
    );
}

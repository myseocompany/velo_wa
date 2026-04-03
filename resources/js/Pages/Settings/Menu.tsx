import AppLayout from '@/Layouts/AppLayout';
import { MenuCategory, MenuItem } from '@/types';
import { DragDropContext, Draggable, Droppable, DropResult } from '@hello-pangea/dnd';
import axios from 'axios';
import {
    Check,
    ChevronLeft,
    Eye,
    GripVertical,
    Pencil,
    Plus,
    Send,
    ToggleLeft,
    ToggleRight,
    Trash2,
    X,
} from 'lucide-react';
import { useEffect, useState } from 'react';

// ─── Category form ────────────────────────────────────────────────────────────

function CategoryForm({
    initial,
    onSave,
    onCancel,
}: {
    initial?: MenuCategory;
    onSave: (cat: MenuCategory) => void;
    onCancel: () => void;
}) {
    const [name, setName]     = useState(initial?.name ?? '');
    const [saving, setSaving] = useState(false);

    async function submit(e: React.FormEvent) {
        e.preventDefault();
        if (!name.trim()) return;
        setSaving(true);
        try {
            const res = initial
                ? await axios.put<{ data: MenuCategory }>(`/api/v1/menu/categories/${initial.id}`, { name: name.trim() })
                : await axios.post<{ data: MenuCategory }>('/api/v1/menu/categories', { name: name.trim() });
            onSave({ ...(res.data.data), items: initial?.items ?? [] });
        } finally {
            setSaving(false);
        }
    }

    return (
        <form onSubmit={submit} className="flex gap-2">
            <input
                autoFocus
                value={name}
                onChange={e => setName(e.target.value)}
                placeholder="Nombre de categoría…"
                className="min-w-0 flex-1 rounded-lg border border-gray-200 px-3 py-1.5 text-sm focus:border-ari-400 focus:outline-none"
            />
            <button type="submit" disabled={saving || !name.trim()}
                className="flex h-8 w-8 items-center justify-center rounded-lg bg-ari-600 text-white disabled:opacity-40">
                <Check className="h-4 w-4" />
            </button>
            <button type="button" onClick={onCancel}
                className="flex h-8 w-8 items-center justify-center rounded-lg border border-gray-200 text-gray-400 hover:bg-gray-50">
                <X className="h-4 w-4" />
            </button>
        </form>
    );
}

// ─── Item form ────────────────────────────────────────────────────────────────

function ItemForm({
    categoryId,
    initial,
    onSave,
    onCancel,
}: {
    categoryId: string;
    initial?: MenuItem;
    onSave: (item: MenuItem) => void;
    onCancel: () => void;
}) {
    const [name, setName]           = useState(initial?.name ?? '');
    const [description, setDesc]    = useState(initial?.description ?? '');
    const [price, setPrice]         = useState(initial?.price ?? '');
    const [currency, setCurrency]   = useState(initial?.currency ?? 'COP');
    const [saving, setSaving]       = useState(false);
    const [errors, setErrors]       = useState<Record<string, string>>({});

    async function submit(e: React.FormEvent) {
        e.preventDefault();
        setSaving(true);
        setErrors({});
        try {
            const payload = {
                menu_category_id: categoryId,
                name: name.trim(),
                description: description.trim() || null,
                price: parseFloat(price),
                currency,
            };
            const res = initial
                ? await axios.put<{ data: MenuItem }>(`/api/v1/menu/items/${initial.id}`, payload)
                : await axios.post<{ data: MenuItem }>('/api/v1/menu/items', payload);
            onSave(res.data.data);
        } catch (err: unknown) {
            if (axios.isAxiosError(err) && err.response?.data?.errors) {
                const e: Record<string, string> = {};
                for (const [k, v] of Object.entries(err.response.data.errors))
                    e[k] = Array.isArray(v) ? (v as string[])[0] : String(v);
                setErrors(e);
            }
        } finally {
            setSaving(false);
        }
    }

    return (
        <form onSubmit={submit} className="space-y-3 rounded-xl border border-ari-100 bg-ari-50/40 p-4">
            <div className="grid grid-cols-2 gap-3">
                <div className="col-span-2">
                    <input
                        autoFocus
                        value={name}
                        onChange={e => setName(e.target.value)}
                        placeholder="Nombre del ítem *"
                        className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-ari-400 focus:outline-none"
                    />
                    {errors.name && <p className="mt-1 text-xs text-red-500">{errors.name}</p>}
                </div>
                <div>
                    <input
                        type="number"
                        min="0"
                        step="0.01"
                        value={price}
                        onChange={e => setPrice(e.target.value)}
                        placeholder="Precio *"
                        className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-ari-400 focus:outline-none"
                    />
                    {errors.price && <p className="mt-1 text-xs text-red-500">{errors.price}</p>}
                </div>
                <div>
                    <select value={currency} onChange={e => setCurrency(e.target.value)}
                        className="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm focus:border-ari-400 focus:outline-none">
                        <option value="COP">COP</option>
                        <option value="USD">USD</option>
                        <option value="EUR">EUR</option>
                    </select>
                </div>
                <div className="col-span-2">
                    <textarea
                        value={description}
                        onChange={e => setDesc(e.target.value)}
                        placeholder="Descripción (opcional)"
                        rows={2}
                        className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-ari-400 focus:outline-none"
                    />
                </div>
            </div>
            <div className="flex justify-end gap-2">
                <button type="button" onClick={onCancel}
                    className="rounded-lg border border-gray-200 px-3 py-1.5 text-sm text-gray-600 hover:bg-gray-50">
                    Cancelar
                </button>
                <button type="submit" disabled={saving || !name.trim() || !price}
                    className="rounded-lg bg-ari-600 px-3 py-1.5 text-sm text-white disabled:opacity-40">
                    {saving ? 'Guardando…' : initial ? 'Guardar cambios' : 'Agregar ítem'}
                </button>
            </div>
        </form>
    );
}

// ─── Preview modal ────────────────────────────────────────────────────────────

function PreviewModal({ messages, onClose, onSendTest, sendingTest }: {
    messages: string[];
    onClose: () => void;
    onSendTest: () => void;
    sendingTest: boolean;
}) {
    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div className="w-full max-w-sm overflow-hidden rounded-2xl bg-white shadow-xl">
                <div className="flex items-center justify-between border-b border-gray-100 px-5 py-4">
                    <h3 className="font-semibold text-gray-900">Vista previa WhatsApp</h3>
                    <button onClick={onClose} className="text-gray-400 hover:text-gray-600">
                        <X className="h-5 w-5" />
                    </button>
                </div>
                <div className="space-y-3 p-5">
                    {messages.map((msg, i) => (
                        <div key={i} className="rounded-2xl rounded-tl-sm bg-white p-3 shadow-sm border border-gray-100">
                            <pre className="whitespace-pre-wrap font-sans text-sm text-gray-800 leading-relaxed">
                                {msg}
                            </pre>
                            {messages.length > 1 && (
                                <p className="mt-1 text-right text-[10px] text-gray-400">Mensaje {i + 1}/{messages.length}</p>
                            )}
                        </div>
                    ))}
                </div>
                <div className="border-t border-gray-100 px-5 py-4">
                    <button
                        onClick={onSendTest}
                        disabled={sendingTest}
                        className="flex w-full items-center justify-center gap-2 rounded-lg bg-green-600 py-2 text-sm font-medium text-white hover:bg-green-700 disabled:opacity-50"
                    >
                        <Send className="h-4 w-4" />
                        {sendingTest ? 'Enviando…' : 'Enviar menú de prueba'}
                    </button>
                    <p className="mt-1.5 text-center text-xs text-gray-400">Se enviará al WhatsApp del owner</p>
                </div>
            </div>
        </div>
    );
}

// ─── Main page ────────────────────────────────────────────────────────────────

export default function MenuSettings() {
    const [categories, setCategories] = useState<MenuCategory[]>([]);
    const [loading, setLoading]       = useState(true);
    const [addingCat, setAddingCat]   = useState(false);
    const [editingCat, setEditingCat] = useState<string | null>(null);
    const [addingItem, setAddingItem] = useState<string | null>(null); // category id
    const [editingItem, setEditingItem] = useState<string | null>(null); // item id
    const [previewMessages, setPreviewMessages] = useState<string[] | null>(null);
    const [loadingPreview, setLoadingPreview]   = useState(false);
    const [sendingTest, setSendingTest]         = useState(false);

    useEffect(() => {
        axios.get<{ data: MenuCategory[] }>('/api/v1/menu/categories')
            .then(r => setCategories(r.data.data))
            .finally(() => setLoading(false));
    }, []);

    // ─── Category handlers ────────────────────────────────────────────────────

    function handleCatSaved(cat: MenuCategory) {
        setCategories(prev => {
            const idx = prev.findIndex(c => c.id === cat.id);
            if (idx >= 0) { const next = [...prev]; next[idx] = cat; return next; }
            return [...prev, cat];
        });
        setAddingCat(false);
        setEditingCat(null);
    }

    async function toggleCategory(cat: MenuCategory) {
        const res = await axios.put<{ data: MenuCategory }>(`/api/v1/menu/categories/${cat.id}`, { is_active: !cat.is_active });
        setCategories(prev => prev.map(c => c.id === cat.id ? { ...res.data.data, items: c.items } : c));
    }

    async function deleteCategory(cat: MenuCategory) {
        if (!confirm(`¿Eliminar la categoría "${cat.name}" y todos sus ítems?`)) return;
        await axios.delete(`/api/v1/menu/categories/${cat.id}`);
        setCategories(prev => prev.filter(c => c.id !== cat.id));
    }

    // ─── Item handlers ────────────────────────────────────────────────────────

    function handleItemSaved(item: MenuItem) {
        setCategories(prev => prev.map(cat => {
            if (cat.id !== item.menu_category_id) return cat;
            const idx = cat.items.findIndex(i => i.id === item.id);
            const items = idx >= 0
                ? cat.items.map(i => i.id === item.id ? item : i)
                : [...cat.items, item];
            return { ...cat, items };
        }));
        setAddingItem(null);
        setEditingItem(null);
    }

    async function toggleItem(item: MenuItem) {
        const res = await axios.patch<{ data: MenuItem }>(`/api/v1/menu/items/${item.id}/toggle`);
        handleItemSaved(res.data.data);
    }

    async function deleteItem(item: MenuItem) {
        if (!confirm(`¿Eliminar "${item.name}"?`)) return;
        await axios.delete(`/api/v1/menu/items/${item.id}`);
        setCategories(prev => prev.map(cat => ({
            ...cat,
            items: cat.items.filter(i => i.id !== item.id),
        })));
    }

    // ─── DnD ─────────────────────────────────────────────────────────────────

    function onDragEnd(result: DropResult) {
        const { source, destination, type } = result;
        if (!destination) return;
        if (source.droppableId === destination.droppableId && source.index === destination.index) return;

        if (type === 'CATEGORY') {
            const reordered = Array.from(categories);
            const [moved] = reordered.splice(source.index, 1);
            reordered.splice(destination.index, 0, moved);
            setCategories(reordered);
            axios.patch('/api/v1/menu/categories/reorder', { ids: reordered.map(c => c.id) });
        } else {
            // type === category ID (items within a category)
            const catId = source.droppableId;
            setCategories(prev => prev.map(cat => {
                if (cat.id !== catId) return cat;
                const items = Array.from(cat.items);
                const [moved] = items.splice(source.index, 1);
                items.splice(destination.index, 0, moved);
                axios.patch('/api/v1/menu/items/reorder', { ids: items.map(i => i.id) });
                return { ...cat, items };
            }));
        }
    }

    // ─── Preview ──────────────────────────────────────────────────────────────

    async function openPreview() {
        setLoadingPreview(true);
        try {
            const res = await axios.get<{ messages: string[] }>('/api/v1/menu/preview');
            setPreviewMessages(res.data.messages);
        } finally {
            setLoadingPreview(false);
        }
    }

    async function sendTest() {
        setSendingTest(true);
        try {
            await axios.post('/api/v1/menu/test');
            alert('Menú enviado a tu WhatsApp.');
        } catch (err: unknown) {
            if (axios.isAxiosError(err)) {
                alert(err.response?.data?.message ?? 'Error al enviar.');
            }
        } finally {
            setSendingTest(false);
        }
    }

    // ─── Render ───────────────────────────────────────────────────────────────

    if (loading) {
        return (
            <AppLayout title="Menú digital">
                <div className="flex h-64 items-center justify-center">
                    <div className="h-6 w-6 animate-spin rounded-full border-2 border-ari-600 border-t-transparent" />
                </div>
            </AppLayout>
        );
    }

    return (
        <AppLayout title="Menú digital">
            <div className="space-y-5 p-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-xl font-semibold text-gray-900">Menú digital</h1>
                        <p className="text-sm text-gray-500">
                            Los clientes que escriban "menú" recibirán este catálogo por WhatsApp.
                        </p>
                    </div>
                    <div className="flex gap-2">
                        <button
                            onClick={openPreview}
                            disabled={loadingPreview}
                            className="flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 disabled:opacity-50"
                        >
                            <Eye className="h-4 w-4" />
                            Vista previa
                        </button>
                        <button
                            onClick={() => setAddingCat(true)}
                            className="flex items-center gap-2 rounded-lg bg-ari-600 px-3 py-2 text-sm font-medium text-white hover:bg-ari-700"
                        >
                            <Plus className="h-4 w-4" />
                            Nueva categoría
                        </button>
                    </div>
                </div>

                {/* Add category form */}
                {addingCat && (
                    <div className="rounded-xl border border-gray-200 bg-white p-4">
                        <CategoryForm onSave={handleCatSaved} onCancel={() => setAddingCat(false)} />
                    </div>
                )}

                {/* Empty state */}
                {categories.length === 0 && !addingCat && (
                    <div className="flex flex-col items-center justify-center rounded-xl border border-dashed border-gray-200 py-16 text-gray-400">
                        <p className="text-sm">Sin categorías aún.</p>
                        <button onClick={() => setAddingCat(true)}
                            className="mt-2 text-sm text-ari-600 hover:underline">
                            Crea la primera categoría
                        </button>
                    </div>
                )}

                {/* Category list with DnD */}
                <DragDropContext onDragEnd={onDragEnd}>
                    <Droppable droppableId="categories" type="CATEGORY">
                        {(provided) => (
                            <div ref={provided.innerRef} {...provided.droppableProps} className="space-y-4">
                                {categories.map((cat, catIndex) => (
                                    <Draggable key={cat.id} draggableId={cat.id} index={catIndex}>
                                        {(drag) => (
                                            <div ref={drag.innerRef} {...drag.draggableProps}
                                                className="overflow-hidden rounded-xl border border-gray-200 bg-white">
                                                {/* Category header */}
                                                <div className="flex items-center gap-2 border-b border-gray-100 bg-gray-50 px-4 py-3">
                                                    <span {...drag.dragHandleProps} className="cursor-grab text-gray-300 hover:text-gray-500">
                                                        <GripVertical className="h-4 w-4" />
                                                    </span>

                                                    {editingCat === cat.id ? (
                                                        <div className="flex-1">
                                                            <CategoryForm
                                                                initial={cat}
                                                                onSave={handleCatSaved}
                                                                onCancel={() => setEditingCat(null)}
                                                            />
                                                        </div>
                                                    ) : (
                                                        <>
                                                            <h3 className={`flex-1 text-sm font-semibold ${cat.is_active ? 'text-gray-900' : 'text-gray-400 line-through'}`}>
                                                                {cat.name}
                                                            </h3>
                                                            <span className="text-xs text-gray-400">{cat.items.length} ítems</span>
                                                            <button onClick={() => toggleCategory(cat)}
                                                                title={cat.is_active ? 'Desactivar' : 'Activar'}
                                                                className="text-gray-400 hover:text-ari-600">
                                                                {cat.is_active
                                                                    ? <ToggleRight className="h-5 w-5 text-ari-500" />
                                                                    : <ToggleLeft className="h-5 w-5" />}
                                                            </button>
                                                            <button onClick={() => setEditingCat(cat.id)}
                                                                className="text-gray-400 hover:text-gray-600">
                                                                <Pencil className="h-3.5 w-3.5" />
                                                            </button>
                                                            <button onClick={() => deleteCategory(cat)}
                                                                className="text-gray-400 hover:text-red-500">
                                                                <Trash2 className="h-3.5 w-3.5" />
                                                            </button>
                                                        </>
                                                    )}
                                                </div>

                                                {/* Items */}
                                                <Droppable droppableId={cat.id} type={cat.id}>
                                                    {(itemDrop) => (
                                                        <div ref={itemDrop.innerRef} {...itemDrop.droppableProps} className="divide-y divide-gray-50">
                                                            {cat.items.map((item, itemIndex) => (
                                                                <Draggable key={item.id} draggableId={item.id} index={itemIndex}>
                                                                    {(itemDrag) => (
                                                                        <div ref={itemDrag.innerRef} {...itemDrag.draggableProps}
                                                                            className="group flex items-center gap-3 px-4 py-3">
                                                                            <span {...itemDrag.dragHandleProps}
                                                                                className="cursor-grab text-gray-200 group-hover:text-gray-400">
                                                                                <GripVertical className="h-4 w-4" />
                                                                            </span>

                                                                            {editingItem === item.id ? (
                                                                                <div className="flex-1">
                                                                                    <ItemForm
                                                                                        categoryId={cat.id}
                                                                                        initial={item}
                                                                                        onSave={handleItemSaved}
                                                                                        onCancel={() => setEditingItem(null)}
                                                                                    />
                                                                                </div>
                                                                            ) : (
                                                                                <>
                                                                                    <div className="min-w-0 flex-1">
                                                                                        <p className={`text-sm font-medium ${item.is_available ? 'text-gray-900' : 'text-gray-400 line-through'}`}>
                                                                                            {item.name}
                                                                                        </p>
                                                                                        {item.description && (
                                                                                            <p className="text-xs text-gray-400 truncate">{item.description}</p>
                                                                                        )}
                                                                                    </div>
                                                                                    <span className="text-sm font-semibold text-gray-700">
                                                                                        {item.formatted_price}
                                                                                    </span>
                                                                                    <button onClick={() => toggleItem(item)}
                                                                                        title={item.is_available ? 'Marcar agotado' : 'Marcar disponible'}
                                                                                        className="text-gray-300 hover:text-ari-500">
                                                                                        {item.is_available
                                                                                            ? <ToggleRight className="h-5 w-5 text-ari-500" />
                                                                                            : <ToggleLeft className="h-5 w-5" />}
                                                                                    </button>
                                                                                    <div className="flex gap-1 opacity-0 group-hover:opacity-100">
                                                                                        <button onClick={() => setEditingItem(item.id)}
                                                                                            className="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600">
                                                                                            <Pencil className="h-3.5 w-3.5" />
                                                                                        </button>
                                                                                        <button onClick={() => deleteItem(item)}
                                                                                            className="rounded p-1 text-gray-400 hover:bg-red-50 hover:text-red-500">
                                                                                            <Trash2 className="h-3.5 w-3.5" />
                                                                                        </button>
                                                                                    </div>
                                                                                </>
                                                                            )}
                                                                        </div>
                                                                    )}
                                                                </Draggable>
                                                            ))}
                                                            {itemDrop.placeholder}

                                                            {/* Add item form or button */}
                                                            {addingItem === cat.id ? (
                                                                <div className="px-4 py-3">
                                                                    <ItemForm
                                                                        categoryId={cat.id}
                                                                        onSave={handleItemSaved}
                                                                        onCancel={() => setAddingItem(null)}
                                                                    />
                                                                </div>
                                                            ) : (
                                                                <button
                                                                    onClick={() => setAddingItem(cat.id)}
                                                                    className="flex w-full items-center gap-1.5 px-4 py-3 text-left text-xs text-gray-400 hover:bg-gray-50 hover:text-ari-600"
                                                                >
                                                                    <Plus className="h-3.5 w-3.5" />
                                                                    Agregar ítem
                                                                </button>
                                                            )}
                                                        </div>
                                                    )}
                                                </Droppable>
                                            </div>
                                        )}
                                    </Draggable>
                                ))}
                                {provided.placeholder}
                            </div>
                        )}
                    </Droppable>
                </DragDropContext>
            </div>

            {/* Preview modal */}
            {previewMessages && (
                <PreviewModal
                    messages={previewMessages}
                    onClose={() => setPreviewMessages(null)}
                    onSendTest={sendTest}
                    sendingTest={sendingTest}
                />
            )}
        </AppLayout>
    );
}

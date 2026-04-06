import AppLayout from '@/Layouts/AppLayout';
import { Contact, Order, OrderStatus, PaginatedData } from '@/types';
import { Head } from '@inertiajs/react';
import { DragDropContext, Draggable, Droppable, DropResult } from '@hello-pangea/dnd';
import axios from 'axios';
import { GripVertical, Package, Plus, Search, User, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

type BoardState = Record<OrderStatus, Order[]>;
interface ApiResponse extends PaginatedData<Order> {}

const STATUSES: { key: OrderStatus; label: string; dot: string; header: string }[] = [
    { key: 'new', label: 'Nuevo', dot: 'bg-slate-400', header: 'bg-slate-100 border-slate-200' },
    { key: 'confirmed', label: 'Confirmado', dot: 'bg-sky-400', header: 'bg-sky-50 border-sky-200' },
    { key: 'preparing', label: 'Preparando', dot: 'bg-amber-400', header: 'bg-amber-50 border-amber-200' },
    { key: 'ready', label: 'Listo', dot: 'bg-violet-400', header: 'bg-violet-50 border-violet-200' },
    { key: 'out_for_delivery', label: 'En camino', dot: 'bg-indigo-400', header: 'bg-indigo-50 border-indigo-200' },
    { key: 'delivered', label: 'Entregado', dot: 'bg-emerald-500', header: 'bg-emerald-50 border-emerald-200' },
    { key: 'cancelled', label: 'Cancelado', dot: 'bg-rose-400', header: 'bg-rose-50 border-rose-200' },
];

function emptyBoard(): BoardState {
    return {
        new: [],
        confirmed: [],
        preparing: [],
        ready: [],
        out_for_delivery: [],
        delivered: [],
        cancelled: [],
    };
}

function groupByStatus(orders: Order[]): BoardState {
    return orders.reduce<BoardState>((acc, order) => {
        acc[order.status].push(order);
        return acc;
    }, emptyBoard());
}

function contactName(c?: Contact) {
    return c?.name ?? c?.push_name ?? c?.phone ?? 'Sin contacto';
}

function fmtValue(v: number, currency = 'COP') {
    if (v >= 1_000_000) return `${currency} ${(v / 1_000_000).toFixed(1)}M`;
    if (v >= 1_000) return `${currency} ${(v / 1_000).toFixed(0)}K`;
    return `${currency} ${v.toLocaleString('es-CO')}`;
}

function OrderCard({ order, moving }: { order: Order; moving: boolean }) {
    return (
        <div className="group rounded-xl border border-gray-200 bg-white p-3.5 shadow-sm transition hover:border-gray-300 hover:shadow-md">
            <div className="flex items-start gap-2">
                <GripVertical size={14} className="mt-0.5 cursor-grab text-gray-300 group-hover:text-gray-400" />
                <div className="min-w-0 flex-1">
                    <p className="truncate text-sm font-semibold text-gray-900">{order.code}</p>
                    <p className="truncate text-xs text-gray-500">{contactName(order.contact)}</p>
                </div>
                {moving && <span className="text-[10px] text-emerald-600">Guardando...</span>}
            </div>

            <div className="ml-5 mt-2 flex items-center gap-2">
                {order.total ? (
                    <span className="rounded-md bg-gray-100 px-1.5 py-0.5 text-xs font-semibold text-gray-700">
                        {fmtValue(Number(order.total), order.currency)}
                    </span>
                ) : (
                    <span className="text-xs text-gray-400">Sin total</span>
                )}
                {order.assignee && (
                    <span className="ml-auto flex items-center gap-1 text-[11px] text-gray-400">
                        <User size={10} />
                        {order.assignee.name.split(' ')[0]}
                    </span>
                )}
            </div>
            {order.notes && (
                <p className="ml-5 mt-1 line-clamp-2 text-[11px] text-gray-500">{order.notes}</p>
            )}
        </div>
    );
}

function CreateOrderModal({
    onClose,
    onCreated,
}: {
    onClose: () => void;
    onCreated: (order: Order) => void;
}) {
    const [total, setTotal] = useState('');
    const [currency, setCurrency] = useState('COP');
    const [notes, setNotes] = useState('');
    const [contactSearch, setContactSearch] = useState('');
    const [contactResults, setContactResults] = useState<Contact[]>([]);
    const [selectedContact, setSelectedContact] = useState<Contact | null>(null);
    const [loadingContacts, setLoadingContacts] = useState(false);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const timeoutRef = useRef<ReturnType<typeof setTimeout>>();

    useEffect(() => {
        if (contactSearch.length < 2) {
            setContactResults([]);
            return;
        }

        clearTimeout(timeoutRef.current);
        timeoutRef.current = setTimeout(async () => {
            setLoadingContacts(true);
            try {
                const res = await axios.get('/api/v1/contacts', { params: { search: contactSearch, per_page: 8 } });
                setContactResults(res.data.data ?? []);
            } finally {
                setLoadingContacts(false);
            }
        }, 250);
    }, [contactSearch]);

    async function submit(e: React.FormEvent) {
        e.preventDefault();
        setError(null);

        if (!selectedContact) {
            setError('Selecciona un contacto');
            return;
        }

        setSaving(true);
        try {
            const res = await axios.post<{ data: Order }>('/api/v1/orders', {
                contact_id: selectedContact.id,
                total: total !== '' ? total : null,
                currency,
                notes: notes || null,
            });
            onCreated(res.data.data);
        } catch {
            setError('No se pudo crear el pedido');
        } finally {
            setSaving(false);
        }
    }

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div className="w-full max-w-lg overflow-hidden rounded-2xl bg-white shadow-xl">
                <div className="flex items-center justify-between border-b border-gray-100 px-5 py-4">
                    <h3 className="font-semibold text-gray-900">Nuevo pedido</h3>
                    <button onClick={onClose} className="text-gray-400 hover:text-gray-600">
                        <X className="h-5 w-5" />
                    </button>
                </div>

                <form onSubmit={submit} className="space-y-4 px-5 py-4">
                    <div>
                        <label className="mb-1 block text-sm font-medium text-gray-700">Contacto</label>
                        <input
                            value={contactSearch}
                            onChange={(e) => setContactSearch(e.target.value)}
                            placeholder="Busca por nombre o teléfono"
                            className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-ari-500 focus:outline-none"
                        />
                        {loadingContacts && <p className="mt-1 text-xs text-gray-400">Buscando...</p>}
                        {contactResults.length > 0 && (
                            <div className="mt-2 max-h-48 overflow-y-auto rounded-xl border border-gray-200">
                                {contactResults.map((c) => (
                                    <button
                                        key={c.id}
                                        type="button"
                                        onClick={() => {
                                            setSelectedContact(c);
                                            setContactSearch(contactName(c));
                                            setContactResults([]);
                                        }}
                                        className="block w-full border-b border-gray-100 px-3 py-2 text-left text-sm hover:bg-gray-50 last:border-b-0"
                                    >
                                        <p className="font-medium text-gray-900">{contactName(c)}</p>
                                        <p className="text-xs text-gray-500">{c.phone ?? 'Sin teléfono'}</p>
                                    </button>
                                ))}
                            </div>
                        )}
                    </div>

                    <div className="grid grid-cols-2 gap-3">
                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">Total</label>
                            <input
                                value={total}
                                onChange={(e) => setTotal(e.target.value)}
                                type="number"
                                min="0"
                                step="0.01"
                                className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-ari-500 focus:outline-none"
                            />
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">Moneda</label>
                            <input
                                value={currency}
                                onChange={(e) => setCurrency(e.target.value.toUpperCase().slice(0, 3))}
                                className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-ari-500 focus:outline-none"
                            />
                        </div>
                    </div>

                    <div>
                        <label className="mb-1 block text-sm font-medium text-gray-700">Notas</label>
                        <textarea
                            value={notes}
                            onChange={(e) => setNotes(e.target.value)}
                            rows={3}
                            className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-ari-500 focus:outline-none"
                        />
                    </div>

                    {error && <p className="text-xs text-red-500">{error}</p>}

                    <div className="flex justify-end gap-2 border-t border-gray-100 pt-4">
                        <button type="button" onClick={onClose} className="rounded-lg px-4 py-2 text-sm text-gray-600 hover:bg-gray-100">
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            disabled={saving}
                            className="rounded-lg bg-ari-600 px-4 py-2 text-sm font-medium text-white hover:bg-ari-700 disabled:opacity-60"
                        >
                            {saving ? 'Guardando...' : 'Crear pedido'}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}

export default function OrdersIndex() {
    const [board, setBoard] = useState<BoardState>(emptyBoard());
    const [loading, setLoading] = useState(true);
    const [search, setSearch] = useState('');
    const [showCreate, setShowCreate] = useState(false);
    const [movingId, setMovingId] = useState<string | null>(null);
    const [moveError, setMoveError] = useState<string | null>(null);

    async function loadOrders() {
        setLoading(true);
        try {
            const res = await axios.get<ApiResponse>('/api/v1/orders', { params: { per_page: 300, search: search || undefined } });
            setBoard(groupByStatus(res.data.data));
        } finally {
            setLoading(false);
        }
    }

    useEffect(() => {
        loadOrders();
    }, []);

    async function onDragEnd(result: DropResult) {
        if (!result.destination) return;

        const from = result.source.droppableId as OrderStatus;
        const to = result.destination.droppableId as OrderStatus;
        if (from === to) return;

        const original = board;
        const next = { ...board };
        const source = [...next[from]];
        const [moved] = source.splice(result.source.index, 1);
        if (!moved) return;

        moved.status = to;
        const target = [...next[to]];
        target.splice(result.destination.index, 0, moved);
        next[from] = source;
        next[to] = target;
        setBoard(next);

        setMovingId(moved.id);
        setMoveError(null);
        try {
            const res = await axios.patch<{ data: Order }>(`/api/v1/orders/${moved.id}/status`, { status: to });
            const updated = res.data.data;
            setBoard((prev) => {
                const b = { ...prev };
                for (const s of STATUSES.map((x) => x.key)) {
                    b[s] = b[s].map((o) => (o.id === updated.id ? updated : o));
                }
                return b;
            });
        } catch (err: unknown) {
            setBoard(original);
            const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message;
            setMoveError(msg ?? 'No se pudo mover el pedido. Intenta de nuevo.');
        } finally {
            setMovingId(null);
        }
    }

    return (
        <AppLayout title="Pedidos">
            <Head title="Pedidos" />

            <div className="space-y-5 p-4 md:p-6">
                <div className="flex flex-col gap-3 rounded-2xl border border-gray-200 bg-white p-4 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h1 className="text-xl font-semibold text-gray-900">Pedidos</h1>
                        <p className="text-sm text-gray-500">Seguimiento de pedidos desde WhatsApp y operación.</p>
                    </div>

                    <div className="flex gap-2">
                        <div className="relative">
                            <Search className="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-gray-400" />
                            <input
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder="Buscar por código o nota"
                                className="w-full rounded-lg border border-gray-200 py-2 pl-9 pr-3 text-sm focus:border-ari-500 focus:outline-none md:w-64"
                            />
                        </div>
                        <button
                            onClick={loadOrders}
                            className="rounded-lg border border-gray-200 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50"
                        >
                            Filtrar
                        </button>
                        <button
                            onClick={() => setShowCreate(true)}
                            className="inline-flex items-center gap-1.5 rounded-lg bg-ari-600 px-3 py-2 text-sm font-medium text-white hover:bg-ari-700"
                        >
                            <Plus size={15} />
                            Nuevo pedido
                        </button>
                    </div>
                </div>

                {moveError && (
                    <div className="flex items-center justify-between rounded-xl border border-rose-200 bg-rose-50 px-4 py-2.5 text-sm text-rose-700">
                        <span>{moveError}</span>
                        <button onClick={() => setMoveError(null)} className="ml-4 text-rose-400 hover:text-rose-600">
                            <X size={14} />
                        </button>
                    </div>
                )}

                {loading ? (
                    <div className="rounded-2xl border border-gray-200 bg-white p-10 text-center text-sm text-gray-500">
                        Cargando pedidos...
                    </div>
                ) : (
                    <DragDropContext onDragEnd={onDragEnd}>
                        <div className="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-4 2xl:grid-cols-7">
                            {STATUSES.map((status) => (
                                <div key={status.key} className="min-h-[240px] rounded-2xl border border-gray-200 bg-gray-50/70">
                                    <div className={`sticky top-0 z-10 flex items-center justify-between rounded-t-2xl border-b px-3 py-2.5 ${status.header}`}>
                                        <div className="flex items-center gap-2">
                                            <span className={`h-2.5 w-2.5 rounded-full ${status.dot}`} />
                                            <p className="text-sm font-semibold text-gray-800">{status.label}</p>
                                        </div>
                                        <span className="rounded-md bg-white/70 px-1.5 py-0.5 text-xs font-medium text-gray-600">
                                            {board[status.key].length}
                                        </span>
                                    </div>

                                    <Droppable droppableId={status.key}>
                                        {(provided) => (
                                            <div
                                                ref={provided.innerRef}
                                                {...provided.droppableProps}
                                                className="space-y-2 p-2.5"
                                            >
                                                {board[status.key].map((order, index) => (
                                                    <Draggable draggableId={order.id} index={index} key={order.id}>
                                                        {(p) => (
                                                            <div ref={p.innerRef} {...p.draggableProps} {...p.dragHandleProps}>
                                                                <OrderCard order={order} moving={movingId === order.id} />
                                                            </div>
                                                        )}
                                                    </Draggable>
                                                ))}
                                                {provided.placeholder}

                                                {board[status.key].length === 0 && (
                                                    <div className="rounded-xl border border-dashed border-gray-300 px-3 py-4 text-center text-xs text-gray-400">
                                                        <Package className="mx-auto mb-1 h-4 w-4" />
                                                        Sin pedidos
                                                    </div>
                                                )}
                                            </div>
                                        )}
                                    </Droppable>
                                </div>
                            ))}
                        </div>
                    </DragDropContext>
                )}
            </div>

            {showCreate && (
                <CreateOrderModal
                    onClose={() => setShowCreate(false)}
                    onCreated={(order) => {
                        setShowCreate(false);
                        setBoard((prev) => ({ ...prev, new: [order, ...prev.new] }));
                    }}
                />
            )}
        </AppLayout>
    );
}


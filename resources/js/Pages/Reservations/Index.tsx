import AppLayout from '@/Layouts/AppLayout';
import { Contact, PaginatedData, Reservation, ReservationStatus } from '@/types';
import { Head } from '@inertiajs/react';
import { DragDropContext, Draggable, Droppable, DropResult } from '@hello-pangea/dnd';
import axios from 'axios';
import { CalendarClock, GripVertical, Plus, Search, User, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

type BoardState = Record<ReservationStatus, Reservation[]>;
interface ApiResponse extends PaginatedData<Reservation> {}
interface BookableUnit {
    id: string;
    name: string;
    type: string;
    settings?: { services?: string[] } | null;
}

const STATUSES: { key: ReservationStatus; label: string; dot: string; header: string }[] = [
    { key: 'requested', label: 'Solicitada', dot: 'bg-slate-400', header: 'bg-slate-100 border-slate-200' },
    { key: 'confirmed', label: 'Confirmada', dot: 'bg-sky-400', header: 'bg-sky-50 border-sky-200' },
    { key: 'seated', label: 'En servicio', dot: 'bg-violet-400', header: 'bg-violet-50 border-violet-200' },
    { key: 'completed', label: 'Completada', dot: 'bg-emerald-500', header: 'bg-emerald-50 border-emerald-200' },
    { key: 'cancelled', label: 'Cancelada', dot: 'bg-rose-400', header: 'bg-rose-50 border-rose-200' },
    { key: 'no_show', label: 'No show', dot: 'bg-gray-500', header: 'bg-gray-100 border-gray-200' },
];

function emptyBoard(): BoardState {
    return { requested: [], confirmed: [], seated: [], completed: [], cancelled: [], no_show: [] };
}

function groupByStatus(items: Reservation[]): BoardState {
    return items.reduce<BoardState>((acc, item) => {
        acc[item.status].push(item);
        return acc;
    }, emptyBoard());
}

function contactName(c?: Contact) {
    return c?.name ?? c?.push_name ?? c?.phone ?? 'Sin contacto';
}

function fmtLocal(iso: string) {
    return new Date(iso).toLocaleString('es-CO', {
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function ReservationCard({ reservation, moving }: { reservation: Reservation; moving: boolean }) {
    return (
        <div className="group rounded-xl border border-gray-200 bg-white p-3.5 shadow-sm transition hover:border-gray-300 hover:shadow-md">
            <div className="flex items-start gap-2">
                <GripVertical size={14} className="mt-0.5 cursor-grab text-gray-300 group-hover:text-gray-400" />
                <div className="min-w-0 flex-1">
                    <p className="truncate text-sm font-semibold text-gray-900">{reservation.code}</p>
                    <p className="truncate text-xs text-gray-500">{contactName(reservation.contact)}</p>
                </div>
                {moving && <span className="text-[10px] text-emerald-600">Guardando...</span>}
            </div>
            <div className="ml-5 mt-2 flex items-center gap-1 text-xs text-gray-500">
                <CalendarClock size={12} />
                <span>{fmtLocal(reservation.starts_at)}</span>
            </div>
            <div className="ml-5 mt-1 flex items-center gap-2 text-[11px] text-gray-500">
                <span>{reservation.party_size} pax</span>
                {reservation.bookable_unit && <span>{reservation.bookable_unit.name}</span>}
                {reservation.service && <span>{reservation.service}</span>}
                {reservation.assignee && (
                    <span className="ml-auto flex items-center gap-1 text-gray-400">
                        <User size={10} />
                        {reservation.assignee.name.split(' ')[0]}
                    </span>
                )}
            </div>
            {reservation.notes && (
                <p className="ml-5 mt-1 line-clamp-2 text-[11px] text-gray-500">{reservation.notes}</p>
            )}
        </div>
    );
}

type Slot = {
    starts_at: string;
    ends_at: string;
    starts_at_local: string;
    ends_at_local: string;
    label: string;
};

function CreateReservationModal({
    onClose,
    onCreated,
}: {
    onClose: () => void;
    onCreated: (reservation: Reservation) => void;
}) {
    const [contactSearch, setContactSearch] = useState('');
    const [contactResults, setContactResults] = useState<Contact[]>([]);
    const [selectedContact, setSelectedContact] = useState<Contact | null>(null);
    const [slotDate, setSlotDate] = useState(new Date().toISOString().slice(0, 10));
    const [slots, setSlots] = useState<Slot[]>([]);
    const [bookableUnits, setBookableUnits] = useState<BookableUnit[]>([]);
    const [selectedUnitId, setSelectedUnitId] = useState('');
    const [service, setService] = useState('');
    const [selectedSlot, setSelectedSlot] = useState('');
    const [partySize, setPartySize] = useState(2);
    const [notes, setNotes] = useState('');
    const [loadingContacts, setLoadingContacts] = useState(false);
    const [loadingSlots, setLoadingSlots] = useState(false);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const searchRef = useRef<ReturnType<typeof setTimeout>>();

    useEffect(() => {
        if (contactSearch.length < 2) {
            setContactResults([]);
            return;
        }
        clearTimeout(searchRef.current);
        searchRef.current = setTimeout(async () => {
            setLoadingContacts(true);
            try {
                const r = await axios.get('/api/v1/contacts', { params: { search: contactSearch, per_page: 8 } });
                setContactResults(r.data.data ?? []);
            } finally {
                setLoadingContacts(false);
            }
        }, 250);
    }, [contactSearch]);

    useEffect(() => {
        axios.get<{ data: BookableUnit[] }>('/api/v1/bookable-units', {
            params: { type: 'professional', active: 1 },
        }).then((res) => {
            setBookableUnits(res.data.data ?? []);
        });
    }, []);

    async function loadSlots() {
        setLoadingSlots(true);
        setSelectedSlot('');
        try {
            const r = await axios.get('/api/v1/reservations/slots', {
                params: { date: slotDate, days: 1, duration_minutes: 60 },
            });
            setSlots(r.data.data ?? []);
        } finally {
            setLoadingSlots(false);
        }
    }

    useEffect(() => {
        loadSlots();
    }, [slotDate]);

    async function submit(e: React.FormEvent) {
        e.preventDefault();
        setError(null);

        if (!selectedContact) {
            setError('Selecciona un contacto');
            return;
        }
        if (!selectedSlot) {
            setError('Selecciona un slot disponible');
            return;
        }

        const slot = slots.find((s) => s.starts_at === selectedSlot);
        if (!slot) {
            setError('Slot inválido');
            return;
        }

        setSaving(true);
        try {
            const res = await axios.post<{ data: Reservation }>('/api/v1/reservations', {
                contact_id: selectedContact.id,
                bookable_unit_id: selectedUnitId || null,
                service: service || null,
                starts_at: slot.starts_at,
                ends_at: slot.ends_at,
                party_size: partySize,
                notes: notes || null,
            });
            onCreated(res.data.data);
        } catch {
            setError('No se pudo crear la reserva');
        } finally {
            setSaving(false);
        }
    }

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div className="w-full max-w-xl overflow-hidden rounded-2xl bg-white shadow-xl">
                <div className="flex items-center justify-between border-b border-gray-100 px-5 py-4">
                    <h3 className="font-semibold text-gray-900">Nueva reserva</h3>
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
                            <label className="mb-1 block text-sm font-medium text-gray-700">Fecha</label>
                            <input
                                type="date"
                                value={slotDate}
                                onChange={(e) => setSlotDate(e.target.value)}
                                className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-ari-500 focus:outline-none"
                            />
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">Personas</label>
                            <input
                                type="number"
                                min={1}
                                max={100}
                                value={partySize}
                                onChange={(e) => setPartySize(Number(e.target.value))}
                                className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-ari-500 focus:outline-none"
                            />
                        </div>
                    </div>

                    {bookableUnits.length > 0 && (
                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">Recurso</label>
                                <select
                                    value={selectedUnitId}
                                    onChange={(e) => setSelectedUnitId(e.target.value)}
                                    className="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm focus:border-ari-500 focus:outline-none"
                                >
                                    <option value="">Sin recurso</option>
                                    {bookableUnits.map((unit) => (
                                        <option key={unit.id} value={unit.id}>{unit.name}</option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">Servicio</label>
                                <input
                                    value={service}
                                    onChange={(e) => setService(e.target.value)}
                                    placeholder="citologia"
                                    className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-ari-500 focus:outline-none"
                                />
                            </div>
                        </div>
                    )}

                    <div>
                        <label className="mb-1 block text-sm font-medium text-gray-700">Slot disponible</label>
                        <select
                            value={selectedSlot}
                            onChange={(e) => setSelectedSlot(e.target.value)}
                            className="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm focus:border-ari-500 focus:outline-none"
                        >
                            <option value="">Seleccionar</option>
                            {slots.map((slot) => (
                                <option key={slot.starts_at} value={slot.starts_at}>
                                    {slot.label}
                                </option>
                            ))}
                        </select>
                        {loadingSlots && <p className="mt-1 text-xs text-gray-400">Cargando slots...</p>}
                        {!loadingSlots && slots.length === 0 && (
                            <p className="mt-1 text-xs text-amber-600">No hay slots disponibles para esa fecha.</p>
                        )}
                    </div>

                    <div>
                        <label className="mb-1 block text-sm font-medium text-gray-700">Notas</label>
                        <textarea
                            rows={2}
                            value={notes}
                            onChange={(e) => setNotes(e.target.value)}
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
                            {saving ? 'Guardando...' : 'Crear reserva'}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}

export default function ReservationsIndex() {
    const [board, setBoard] = useState<BoardState>(emptyBoard());
    const [loading, setLoading] = useState(true);
    const [search, setSearch] = useState('');
    const [showCreate, setShowCreate] = useState(false);
    const [movingId, setMovingId] = useState<string | null>(null);

    async function loadReservations() {
        setLoading(true);
        try {
            const res = await axios.get<ApiResponse>('/api/v1/reservations', { params: { per_page: 300, search: search || undefined } });
            setBoard(groupByStatus(res.data.data));
        } finally {
            setLoading(false);
        }
    }

    useEffect(() => {
        loadReservations();
    }, []);

    async function onDragEnd(result: DropResult) {
        if (!result.destination) return;
        const from = result.source.droppableId as ReservationStatus;
        const to = result.destination.droppableId as ReservationStatus;
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
        try {
            const res = await axios.patch<{ data: Reservation }>(`/api/v1/reservations/${moved.id}/status`, { status: to });
            const updated = res.data.data;
            setBoard((prev) => {
                const b = { ...prev };
                for (const s of STATUSES.map((x) => x.key)) {
                    b[s] = b[s].map((r) => (r.id === updated.id ? updated : r));
                }
                return b;
            });
        } catch {
            setBoard(original);
        } finally {
            setMovingId(null);
        }
    }

    return (
        <AppLayout title="Reservas">
            <Head title="Reservas" />
            <div className="space-y-5 p-4 md:p-6">
                <div className="flex flex-col gap-3 rounded-2xl border border-gray-200 bg-white p-4 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h1 className="text-xl font-semibold text-gray-900">Reservas</h1>
                        <p className="text-sm text-gray-500">Booking con slots de disponibilidad.</p>
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
                        <button onClick={loadReservations} className="rounded-lg border border-gray-200 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">
                            Filtrar
                        </button>
                        <button
                            onClick={() => setShowCreate(true)}
                            className="inline-flex items-center gap-1.5 rounded-lg bg-ari-600 px-3 py-2 text-sm font-medium text-white hover:bg-ari-700"
                        >
                            <Plus size={15} />
                            Nueva reserva
                        </button>
                    </div>
                </div>

                {loading ? (
                    <div className="rounded-2xl border border-gray-200 bg-white p-10 text-center text-sm text-gray-500">
                        Cargando reservas...
                    </div>
                ) : (
                    <DragDropContext onDragEnd={onDragEnd}>
                        <div className="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-6">
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
                                            <div ref={provided.innerRef} {...provided.droppableProps} className="space-y-2 p-2.5">
                                                {board[status.key].map((reservation, index) => (
                                                    <Draggable draggableId={reservation.id} index={index} key={reservation.id}>
                                                        {(p) => (
                                                            <div ref={p.innerRef} {...p.draggableProps} {...p.dragHandleProps}>
                                                                <ReservationCard reservation={reservation} moving={movingId === reservation.id} />
                                                            </div>
                                                        )}
                                                    </Draggable>
                                                ))}
                                                {provided.placeholder}
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
                <CreateReservationModal
                    onClose={() => setShowCreate(false)}
                    onCreated={(reservation) => {
                        setShowCreate(false);
                        setBoard((prev) => ({ ...prev, requested: [reservation, ...prev.requested] }));
                    }}
                />
            )}
        </AppLayout>
    );
}

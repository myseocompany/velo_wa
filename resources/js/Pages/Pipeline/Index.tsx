import AppLayout from '@/Layouts/AppLayout';
import { Contact, DealStage, PaginatedData, PipelineDeal, Task, User } from '@/types';
import { Head } from '@inertiajs/react';
import { DragDropContext, Draggable, Droppable, DropResult } from '@hello-pangea/dnd';
import axios from 'axios';
import {
    AlertCircle,
    BarChart2,
    Briefcase,
    Calendar,
    Check,
    CheckSquare,
    ChevronDown,
    ChevronUp,
    GripVertical,
    Pencil,
    Plus,
    Search,
    Timer,
    Trash2,
    TrendingUp,
    Trophy,
    User as UserIcon,
    X,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import {
    Bar, BarChart, Cell, ResponsiveContainer, Tooltip, XAxis, YAxis,
} from 'recharts';

// ─── Types ────────────────────────────────────────────────────────────────────

interface StageSummary { stage: DealStage; label: string; count: number; total_value: number; }
interface PipelineSummary {
    by_stage: StageSummary[];
    active_pipeline: number;
    total_won: number;
    total_lost: number;
    stage_durations?: Record<string, number | null>;
}
interface ApiResponse extends PaginatedData<PipelineDeal> {}

type BoardState = Record<DealStage, PipelineDeal[]>;

// ─── Constants ────────────────────────────────────────────────────────────────

// Hex colors matching Tailwind classes (for recharts)
const STAGE_HEX: Record<string, string> = {
    lead:        '#94a3b8', // slate-400
    qualified:   '#38bdf8', // sky-400
    proposal:    '#fbbf24', // amber-400
    negotiation: '#a78bfa', // violet-400
    closed_won:  '#10b981', // emerald-500
    closed_lost: '#f87171', // rose-400
};

const STAGE_WEIGHTS: Record<string, number> = {
    lead: 0.10, qualified: 0.25, proposal: 0.50, negotiation: 0.75,
};

const STAGES: { key: DealStage; label: string; color: string; dot: string; header: string }[] = [
    { key: 'lead',        label: 'Lead',        color: 'text-slate-600',   dot: 'bg-slate-400',    header: 'bg-slate-100 border-slate-200' },
    { key: 'qualified',   label: 'Calificado',  color: 'text-sky-600',     dot: 'bg-sky-400',      header: 'bg-sky-50 border-sky-200' },
    { key: 'proposal',    label: 'Propuesta',   color: 'text-amber-600',   dot: 'bg-amber-400',    header: 'bg-amber-50 border-amber-200' },
    { key: 'negotiation', label: 'Negociación', color: 'text-violet-600',  dot: 'bg-violet-400',   header: 'bg-violet-50 border-violet-200' },
    { key: 'closed_won',  label: 'Ganado',      color: 'text-emerald-600', dot: 'bg-emerald-500',  header: 'bg-emerald-50 border-emerald-200' },
    { key: 'closed_lost', label: 'Perdido',     color: 'text-rose-500',    dot: 'bg-rose-400',     header: 'bg-rose-50 border-rose-200' },
];

const STAGE_MAP = Object.fromEntries(STAGES.map(s => [s.key, s])) as Record<DealStage, typeof STAGES[0]>;

function emptyBoard(): BoardState {
    return { lead: [], qualified: [], proposal: [], negotiation: [], closed_won: [], closed_lost: [] };
}

function groupByStage(deals: PipelineDeal[]): BoardState {
    return deals.reduce<BoardState>((acc, d) => { acc[d.stage].push(d); return acc; }, emptyBoard());
}

function fmtValue(v: number, currency = 'COP') {
    if (v >= 1_000_000) return `${currency} ${(v / 1_000_000).toFixed(1)}M`;
    if (v >= 1_000)     return `${currency} ${(v / 1_000).toFixed(0)}K`;
    return `${currency} ${v.toLocaleString('es-CO')}`;
}

function contactName(c?: Contact) {
    return c?.name ?? c?.push_name ?? c?.phone ?? '—';
}

// ─── Deal Card ────────────────────────────────────────────────────────────────

function DealCard({ deal, moving, onEdit }: { deal: PipelineDeal; moving: boolean; onEdit: () => void }) {
    const stage = STAGE_MAP[deal.stage];
    return (
        <div className="group bg-white rounded-xl border border-gray-200 shadow-sm hover:shadow-md hover:border-gray-300 transition-all duration-150 p-3.5">
            {/* Title row */}
            <div className="flex items-start gap-2">
                <GripVertical size={14} className="mt-0.5 shrari-0 text-gray-300 group-hover:text-gray-400 cursor-grab" />
                <p className="flex-1 text-sm font-medium text-gray-900 leading-snug line-clamp-2">{deal.title}</p>
                <button
                    onClick={onEdit}
                    className="shrink-0 opacity-0 group-hover:opacity-100 flex h-11 w-11 items-center justify-center rounded-lg text-gray-400 hover:text-gray-700 hover:bg-gray-100 transition"
                >
                    <Pencil size={13} />
                </button>
            </div>

            {/* Contact */}
            <p className="mt-1.5 ml-5 text-xs text-gray-500 truncate">{contactName(deal.contact)}</p>

            {/* Footer */}
            <div className="mt-2.5 ml-5 flex items-center gap-2 flex-wrap">
                {deal.value ? (
                    <span className="text-xs font-semibold text-gray-700 bg-gray-100 rounded-md px-1.5 py-0.5">
                        {fmtValue(Number(deal.value), deal.currency)}
                    </span>
                ) : (
                    <span className="text-xs text-gray-300">Sin valor</span>
                )}
                {deal.assignee && (
                    <span className="flex items-center gap-1 text-[11px] text-gray-400 ml-auto">
                        <UserIcon size={10} />{deal.assignee.name.split(' ')[0]}
                    </span>
                )}
                {moving && <span className="text-[10px] text-emerald-600 ml-auto animate-pulse">Guardando…</span>}
            </div>

            {/* Won / Lost badge */}
            {deal.won_product && (
                <p className="mt-1.5 ml-5 text-[11px] text-emerald-600 truncate flex items-center gap-1">
                    <Trophy size={10} />{deal.won_product}
                </p>
            )}
            {deal.lost_reason && (
                <p className="mt-1.5 ml-5 text-[11px] text-rose-500 truncate flex items-center gap-1">
                    <AlertCircle size={10} />{deal.lost_reason}
                </p>
            )}
        </div>
    );
}

// ─── Deal Modal ───────────────────────────────────────────────────────────────

interface ModalProps {
    deal: PipelineDeal | null;
    agents: User[];
    defaultStage: DealStage;
    onClose: () => void;
    onSaved: (d: PipelineDeal) => void;
    onDeleted: (id: string) => void;
}

function DealModal({ deal, agents, defaultStage, onClose, onSaved, onDeleted }: ModalProps) {
    const isEdit = deal !== null;

    const [title, setTitle]           = useState(deal?.title ?? '');
    const [value, setValue]           = useState(deal?.value ?? '');
    const [currency, setCurrency]     = useState(deal?.currency ?? 'COP');
    const [assignedTo, setAssignedTo] = useState(deal?.assigned_to ?? '');
    const [notes, setNotes]           = useState(deal?.notes ?? '');
    const [wonProduct, setWonProduct] = useState(deal?.won_product ?? '');
    const [lostReason, setLostReason] = useState(deal?.lost_reason ?? '');

    // Contact search (create only)
    const [contactSearch, setContactSearch]   = useState('');
    const [contactResults, setContactResults] = useState<Contact[]>([]);
    const [selectedContact, setSelected]      = useState<Contact | null>(deal?.contact ?? null);
    const [contactLoading, setContactLoading] = useState(false);
    const searchRef = useRef<ReturnType<typeof setTimeout>>();

    const [saving, setSaving]     = useState(false);
    const [deleting, setDeleting] = useState(false);
    const [errors, setErrors]     = useState<Record<string, string>>({});

    // Tasks for this deal
    const [tasks, setTasks]           = useState<Task[]>([]);
    const [tasksLoading, setTasksLoading] = useState(false);
    const [newTaskTitle, setNewTaskTitle] = useState('');
    const [addingTask, setAddingTask]     = useState(false);
    const [savingTask, setSavingTask]     = useState(false);

    useEffect(() => {
        if (!deal?.id) return;
        setTasksLoading(true);
        axios.get('/api/v1/tasks', { params: { deal_id: deal.id, per_page: 10 } })
            .then(r => setTasks(r.data.data ?? []))
            .finally(() => setTasksLoading(false));
    }, [deal?.id]);

    async function createDealTask(e: React.FormEvent) {
        e.preventDefault();
        if (!newTaskTitle.trim() || !deal?.id) return;
        setSavingTask(true);
        try {
            const res = await axios.post<{ data: Task }>('/api/v1/tasks', {
                title: newTaskTitle.trim(),
                deal_id: deal.id,
                contact_id: deal.contact_id,
            });
            setTasks(prev => [res.data.data, ...prev]);
            setNewTaskTitle('');
            setAddingTask(false);
        } finally {
            setSavingTask(false);
        }
    }

    async function completeDealTask(task: Task) {
        const res = await axios.patch<{ data: Task }>(`/api/v1/tasks/${task.id}/complete`);
        setTasks(prev => prev.map(t => t.id === task.id ? res.data.data : t));
    }

    const stage = deal?.stage ?? defaultStage;
    const isClosedWon  = stage === 'closed_won';
    const isClosedLost = stage === 'closed_lost';

    // Contact search debounce
    useEffect(() => {
        if (isEdit || contactSearch.length < 2) { setContactResults([]); return; }
        clearTimeout(searchRef.current);
        searchRef.current = setTimeout(async () => {
            setContactLoading(true);
            const r = await axios.get('/api/v1/contacts', { params: { search: contactSearch, per_page: 8 } });
            setContactResults(r.data.data);
            setContactLoading(false);
        }, 280);
    }, [contactSearch, isEdit]);

    async function submit(e: React.FormEvent) {
        e.preventDefault();
        if (!isEdit && !selectedContact) { setErrors({ contact_id: 'Selecciona un contacto' }); return; }
        setSaving(true); setErrors({});
        try {
            const body: Record<string, unknown> = {
                title, value: value !== '' ? value : null, currency,
                assigned_to: assignedTo || null,
                notes: notes || null,
                won_product: wonProduct || null,
                lost_reason: lostReason || null,
                contact_id: isEdit ? deal!.contact_id : selectedContact!.id,
                ...(!isEdit && { stage }),
            };
            const res = isEdit
                ? await axios.put(`/api/v1/pipeline/deals/${deal!.id}`, body)
                : await axios.post('/api/v1/pipeline/deals', body);
            onSaved(res.data.data);
        } catch (err: unknown) {
            if (axios.isAxiosError(err) && err.response?.data?.errors) {
                const e: Record<string, string> = {};
                for (const [k, v] of Object.entries(err.response.data.errors))
                    e[k] = Array.isArray(v) ? (v as string[])[0] : String(v);
                setErrors(e);
            }
        } finally { setSaving(false); }
    }

    async function remove() {
        if (!deal || !confirm(`¿Eliminar "${deal.title}"?`)) return;
        setDeleting(true);
        await axios.delete(`/api/v1/pipeline/deals/${deal.id}`);
        onDeleted(deal.id);
    }

    return (
        <div className="fixed inset-0 z-50 flex items-end sm:items-center justify-center bg-black/40 backdrop-blur-sm p-4">
            <div className="bg-white rounded-2xl shadow-2xl w-full max-w-md max-h-[90vh] flex flex-col">

                {/* Header */}
                <div className="flex items-center justify-between px-5 py-4 border-b">
                    <div className="flex items-center gap-2">
                        <span className={`w-2 h-2 rounded-full ${STAGE_MAP[stage].dot}`} />
                        <h2 className="font-semibold text-gray-900">{isEdit ? 'Editar deal' : `Nuevo deal · ${STAGE_MAP[stage].label}`}</h2>
                    </div>
                    <button onClick={onClose} className="flex h-11 w-11 items-center justify-center rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100">
                        <X size={18} />
                    </button>
                </div>

                {/* Body */}
                <form onSubmit={submit} className="overflow-y-auto flex-1 px-5 py-4 space-y-4">

                    {/* Título */}
                    <div>
                        <label className="text-xs font-medium text-gray-500 uppercase tracking-wide">Título *</label>
                        <input value={title} onChange={e => setTitle(e.target.value)} required
                            className="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"
                            placeholder="Ej: Propuesta empresa XYZ" />
                        {errors.title && <p className="text-xs text-red-500 mt-1">{errors.title}</p>}
                    </div>

                    {/* Contacto */}
                    {isEdit ? (
                        <div>
                            <label className="text-xs font-medium text-gray-500 uppercase tracking-wide">Contacto</label>
                            <div className="mt-1 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-600">
                                {contactName(deal?.contact)}
                            </div>
                        </div>
                    ) : (
                        <div className="relative">
                            <label className="text-xs font-medium text-gray-500 uppercase tracking-wide">Contacto *</label>
                            {selectedContact ? (
                                <div className="mt-1 flex items-center justify-between rounded-lg border border-emerald-300 bg-emerald-50 px-3 py-2">
                                    <div>
                                        <p className="text-sm font-medium text-gray-900">{contactName(selectedContact)}</p>
                                        {selectedContact.phone && <p className="text-xs text-gray-400">{selectedContact.phone}</p>}
                                    </div>
                                    <button type="button" onClick={() => setSelected(null)} className="text-gray-400 hover:text-red-400">
                                        <X size={14} />
                                    </button>
                                </div>
                            ) : (
                                <>
                                    <div className="mt-1 relative">
                                        <Search size={13} className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
                                        <input value={contactSearch} onChange={e => setContactSearch(e.target.value)}
                                            className="w-full rounded-lg border border-gray-300 pl-8 pr-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"
                                            placeholder="Buscar por nombre o teléfono…" />
                                    </div>
                                    {(contactLoading || contactResults.length > 0) && (
                                        <div className="absolute z-20 left-0 right-0 mt-1 bg-white border border-gray-200 rounded-xl shadow-lg overflow-hidden">
                                            {contactLoading && <p className="text-xs text-gray-400 px-3 py-2">Buscando…</p>}
                                            {contactResults.map(c => (
                                                <button key={c.id} type="button"
                                                    onClick={() => { setSelected(c); setContactSearch(''); setContactResults([]); }}
                                                    className="w-full text-left px-3 py-2 hover:bg-gray-50 flex justify-between items-center">
                                                    <span className="text-sm font-medium">{contactName(c)}</span>
                                                    <span className="text-xs text-gray-400">{c.phone}</span>
                                                </button>
                                            ))}
                                        </div>
                                    )}
                                </>
                            )}
                            {errors.contact_id && <p className="text-xs text-red-500 mt-1">{errors.contact_id}</p>}
                        </div>
                    )}

                    {/* Valor + Moneda */}
                    <div className="flex gap-3">
                        <div className="flex-1">
                            <label className="text-xs font-medium text-gray-500 uppercase tracking-wide">Valor</label>
                            <input type="number" min="0" value={value} onChange={e => setValue(e.target.value)}
                                className="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"
                                placeholder="0" />
                        </div>
                        <div className="w-24">
                            <label className="text-xs font-medium text-gray-500 uppercase tracking-wide">Moneda</label>
                            <select value={currency} onChange={e => setCurrency(e.target.value)}
                                className="mt-1 w-full rounded-lg border border-gray-300 px-2 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                                <option>COP</option><option>USD</option><option>EUR</option>
                            </select>
                        </div>
                    </div>

                    {/* Asignado */}
                    <div>
                        <label className="text-xs font-medium text-gray-500 uppercase tracking-wide">Asignado a</label>
                        <div className="mt-1 relative">
                            <select value={assignedTo} onChange={e => setAssignedTo(e.target.value)}
                                className="w-full appearance-none rounded-lg border border-gray-300 px-3 pr-8 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                                <option value="">Sin asignar</option>
                                {agents.map(a => <option key={a.id} value={a.id}>{a.name}</option>)}
                            </select>
                            <ChevronDown size={13} className="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none" />
                        </div>
                    </div>

                    {/* Won product */}
                    {isClosedWon && (
                        <div>
                            <label className="text-xs font-medium text-gray-500 uppercase tracking-wide">Producto / Servicio ganado</label>
                            <input value={wonProduct} onChange={e => setWonProduct(e.target.value)}
                                className="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"
                                placeholder="Ej: Plan Premium" />
                        </div>
                    )}

                    {/* Lost reason */}
                    {isClosedLost && (
                        <div>
                            <label className="text-xs font-medium text-gray-500 uppercase tracking-wide">Razón de pérdida</label>
                            <input value={lostReason} onChange={e => setLostReason(e.target.value)}
                                className="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"
                                placeholder="Ej: Precio, competidor, sin presupuesto…" />
                        </div>
                    )}

                    {/* Notas */}
                    <div>
                        <label className="text-xs font-medium text-gray-500 uppercase tracking-wide">Notas</label>
                        <textarea value={notes} onChange={e => setNotes(e.target.value)} rows={3}
                            className="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 resize-none"
                            placeholder="Contexto relevante…" />
                    </div>

                    {/* Tasks — only shown when editing an existing deal */}
                    {isEdit && (
                        <div className="border-t border-gray-100 pt-4">
                            <div className="mb-2 flex items-center justify-between">
                                <label className="flex items-center gap-1.5 text-xs font-medium uppercase tracking-wide text-gray-500">
                                    <CheckSquare size={12} /> Tareas
                                </label>
                                <button
                                    type="button"
                                    onClick={() => setAddingTask(v => !v)}
                                    className="flex items-center gap-1 text-xs text-ari-600 hover:text-ari-700"
                                >
                                    <Plus size={12} /> Nueva
                                </button>
                            </div>

                            {addingTask && (
                                <form onSubmit={createDealTask} className="mb-2 flex gap-1.5">
                                    <input
                                        autoFocus
                                        value={newTaskTitle}
                                        onChange={e => setNewTaskTitle(e.target.value)}
                                        placeholder="Título de la tarea…"
                                        className="min-w-0 flex-1 rounded-lg border border-gray-200 px-2.5 py-1.5 text-xs focus:border-ari-400 focus:outline-none"
                                    />
                                    <button type="submit" disabled={savingTask || !newTaskTitle.trim()}
                                        className="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-ari-600 text-white disabled:opacity-40">
                                        <Check size={12} />
                                    </button>
                                    <button type="button" onClick={() => setAddingTask(false)}
                                        className="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg border border-gray-200 text-gray-400">
                                        <X size={12} />
                                    </button>
                                </form>
                            )}

                            {tasksLoading && <p className="text-xs text-gray-400">Cargando…</p>}

                            {!tasksLoading && tasks.length === 0 && !addingTask && (
                                <p className="text-xs text-gray-400">Sin tareas en este deal</p>
                            )}

                            {!tasksLoading && tasks.map(task => (
                                <div key={task.id} className="flex items-start gap-2 py-1.5">
                                    <button
                                        type="button"
                                        onClick={() => completeDealTask(task)}
                                        disabled={!!task.completed_at}
                                        className={`mt-0.5 flex h-4 w-4 shrink-0 items-center justify-center rounded-full border-2 transition-colors ${
                                            task.completed_at
                                                ? 'border-ari-400 bg-ari-400 text-white'
                                                : 'border-gray-300 hover:border-ari-400'
                                        }`}
                                    >
                                        {task.completed_at && <Check size={9} />}
                                    </button>
                                    <div className="min-w-0 flex-1">
                                        <p className={`text-xs ${task.completed_at ? 'text-gray-400 line-through' : 'text-gray-800'}`}>
                                            {task.title}
                                        </p>
                                        {task.due_at && (
                                            <p className={`flex items-center gap-0.5 text-[10px] ${
                                                task.is_overdue && !task.completed_at ? 'text-red-500' : 'text-gray-400'
                                            }`}>
                                                <Calendar size={9} />
                                                {new Date(task.due_at).toLocaleString('es-CO', {
                                                    month: 'short', day: 'numeric',
                                                    hour: '2-digit', minute: '2-digit',
                                                })}
                                            </p>
                                        )}
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </form>

                {/* Footer */}
                <div className="flex flex-col-reverse gap-2 px-5 py-4 border-t bg-gray-50 rounded-b-2xl sm:flex-row sm:items-center sm:justify-between">
                    {isEdit ? (
                        <button type="button" onClick={remove} disabled={deleting}
                            className="flex items-center justify-center gap-1.5 min-h-11 w-full sm:w-auto text-sm text-red-500 hover:text-red-700 disabled:opacity-50">
                            <Trash2 size={14} />{deleting ? 'Eliminando…' : 'Eliminar'}
                        </button>
                    ) : <span className="hidden sm:block" />}
                    <div className="flex flex-col-reverse gap-2 sm:flex-row">
                        <button type="button" onClick={onClose}
                            className="min-h-11 w-full sm:w-auto px-4 py-2 text-sm rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-100">
                            Cancelar
                        </button>
                        <button onClick={submit as unknown as React.MouseEventHandler} disabled={saving}
                            className="min-h-11 w-full sm:w-auto px-4 py-2 text-sm rounded-lg bg-emerald-600 text-white hover:bg-emerald-700 disabled:opacity-50 font-medium">
                            {saving ? 'Guardando…' : isEdit ? 'Guardar' : 'Crear deal'}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    );
}

// ─── Summary Bar ──────────────────────────────────────────────────────────────

function SummaryBar({ summary }: { summary: PipelineSummary }) {
    const total = summary.total_won + summary.total_lost;
    const winRate = total > 0 ? Math.round((summary.total_won / total) * 100) : null;

    const weighted = summary.by_stage
        .filter(s => STAGE_WEIGHTS[s.stage] !== undefined)
        .reduce((acc, s) => acc + s.total_value * STAGE_WEIGHTS[s.stage], 0);

    const cards = [
        { label: 'Pipeline activo',  value: fmtValue(summary.active_pipeline), icon: <Briefcase size={16} />,    bg: 'bg-white',       text: 'text-gray-900',    sub: 'text-gray-500' },
        { label: 'Pronóstico pond.', value: fmtValue(weighted),                 icon: <TrendingUp size={16} />,   bg: 'bg-violet-50',   text: 'text-violet-900',  sub: 'text-violet-500' },
        { label: 'Ganado',           value: fmtValue(summary.total_won),        icon: <Trophy size={16} />,       bg: 'bg-emerald-50',  text: 'text-emerald-800', sub: 'text-emerald-600' },
        { label: 'Perdido',          value: fmtValue(summary.total_lost),       icon: <AlertCircle size={16} />,  bg: 'bg-rose-50',     text: 'text-rose-800',    sub: 'text-rose-500' },
        { label: 'Tasa de cierre',   value: winRate !== null ? `${winRate}%` : '—', icon: <BarChart2 size={16} />, bg: 'bg-white',      text: 'text-gray-900',    sub: 'text-gray-500' },
    ];

    return (
        <div className="grid grid-cols-2 md:grid-cols-5 gap-3">
            {cards.map(c => (
                <div key={c.label} className={`${c.bg} rounded-xl border border-gray-200 px-4 py-3 flex items-center gap-3 shadow-sm`}>
                    <span className={`${c.sub} shrink-0`}>{c.icon}</span>
                    <div className="min-w-0">
                        <p className={`text-xs ${c.sub}`}>{c.label}</p>
                        <p className={`text-base font-bold ${c.text} leading-tight mt-0.5 truncate`}>{c.value}</p>
                    </div>
                </div>
            ))}
        </div>
    );
}

// ─── Funnel Section ───────────────────────────────────────────────────────────

function formatDuration(hours: number | null | undefined): string {
    if (hours == null || hours <= 0) return '—';
    if (hours < 1) return `${Math.round(hours * 60)}m`;
    if (hours < 24) return `${hours.toFixed(1)}h`;
    return `${(hours / 24).toFixed(1)}d`;
}

function FunnelSection({ summary, open, onToggle }: { summary: PipelineSummary; open: boolean; onToggle: () => void }) {
    const activeStages = STAGES.filter(s => s.key !== 'closed_won' && s.key !== 'closed_lost');

    const chartData = activeStages.map(s => {
        const row = summary.by_stage.find(b => b.stage === s.key);
        return { label: s.label, key: s.key, count: row?.count ?? 0 };
    });

    const maxCount = Math.max(...chartData.map(d => d.count), 1);

    const durationLabels: Record<string, string> = {
        lead: 'Lead → Calificado',
        qualified: 'Calificado → Propuesta',
        proposal: 'Propuesta → Negociación',
        negotiation: 'Negociación → Cierre',
    };

    return (
        <div className="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
            <button
                onClick={onToggle}
                className="w-full flex items-center justify-between px-4 py-3 text-left hover:bg-gray-50 transition"
            >
                <div className="flex items-center gap-2">
                    <BarChart2 size={15} className="text-gray-400" />
                    <span className="text-sm font-semibold text-gray-700">Embudo de conversión</span>
                </div>
                {open ? <ChevronUp size={15} className="text-gray-400" /> : <ChevronDown size={15} className="text-gray-400" />}
            </button>

            {open && (
                <div className="px-4 pb-4 grid grid-cols-1 md:grid-cols-2 gap-6">
                    {/* Horizontal bar chart */}
                    <div>
                        <ResponsiveContainer width="100%" height={160}>
                            <BarChart data={chartData} layout="vertical" margin={{ left: 4, right: 32, top: 4, bottom: 4 }}>
                                <XAxis type="number" hide domain={[0, maxCount]} />
                                <YAxis dataKey="label" type="category" width={90} tick={{ fontSize: 12, fill: '#6b7280' }} axisLine={false} tickLine={false} />
                                <Tooltip
                                    cursor={{ fill: '#f3f4f6' }}
                                    contentStyle={{ fontSize: 12, borderRadius: 8, border: '1px solid #e5e7eb' }}
                                    content={({ payload }) => {
                                        if (!payload?.length) return null;
                                        const entry = payload[0];
                                        const value = entry.value as number;
                                        const label = (entry.payload as { label: string }).label;
                                        const idx = chartData.findIndex(d => d.label === label);
                                        const prev = idx > 0 ? chartData[idx - 1].count : null;
                                        const pct = prev && prev > 0 ? ` · ${Math.round((value / prev) * 100)}% conv.` : '';
                                        return (
                                            <div className="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs shadow-sm">
                                                <span className="font-medium">{label}</span>: {value} deals{pct}
                                            </div>
                                        );
                                    }}
                                />
                                <Bar dataKey="count" radius={[0, 6, 6, 0]} maxBarSize={24} label={{ position: 'right', fontSize: 11, fill: '#6b7280' }}>
                                    {chartData.map(entry => (
                                        <Cell key={entry.key} fill={STAGE_HEX[entry.key]} />
                                    ))}
                                </Bar>
                            </BarChart>
                        </ResponsiveContainer>

                        {/* Conversion % between stages */}
                        <div className="flex justify-around mt-1">
                            {chartData.slice(1).map((d, i) => {
                                const prev = chartData[i].count;
                                const pct = prev > 0 ? Math.round((d.count / prev) * 100) : null;
                                return (
                                    <div key={d.key} className="text-center">
                                        <p className="text-[10px] text-gray-400">{chartData[i].label}→</p>
                                        <p className={`text-xs font-bold ${pct !== null && pct >= 50 ? 'text-emerald-600' : 'text-amber-500'}`}>
                                            {pct !== null ? `${pct}%` : '—'}
                                        </p>
                                    </div>
                                );
                            })}
                        </div>
                    </div>

                    {/* Stage durations */}
                    <div>
                        <p className="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-2 flex items-center gap-1">
                            <Timer size={12} /> Tiempo promedio por etapa
                        </p>
                        <div className="space-y-2">
                            {Object.entries(durationLabels).map(([stage, label]) => {
                                const hours = summary.stage_durations?.[stage] ?? null;
                                return (
                                    <div key={stage} className="flex items-center justify-between text-sm">
                                        <span className="text-gray-600">{label}</span>
                                        <span className="font-semibold text-gray-800 tabular-nums">{formatDuration(hours)}</span>
                                    </div>
                                );
                            })}
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}

// ─── Page ─────────────────────────────────────────────────────────────────────

export default function PipelineIndex() {
    const [board, setBoard]       = useState<BoardState>(emptyBoard);
    const [summary, setSummary]   = useState<PipelineSummary | null>(null);
    const [agents, setAgents]     = useState<User[]>([]);
    const [loading, setLoading]   = useState(true);
    const [error, setError]       = useState<string | null>(null);
    const [movingId, setMovingId] = useState<string | null>(null);

    // Filters
    const [search, setSearch]           = useState('');
    const [agentFilter, setAgentFilter] = useState('');
    const [dateFrom, setDateFrom]       = useState('');
    const [dateTo, setDateTo]           = useState('');
    const [valueMin, setValueMin]       = useState('');
    const [valueMax, setValueMax]       = useState('');
    const filterRef = useRef<ReturnType<typeof setTimeout>>();

    // UI state
    const [funnelOpen, setFunnelOpen]   = useState(false);

    // Modal
    const [modalOpen, setModalOpen]       = useState(false);
    const [editDeal, setEditDeal]         = useState<PipelineDeal | null>(null);
    const [createStage, setCreateStage]   = useState<DealStage>('lead');

    // ── Data fetching ──────────────────────────────────────────────────────────

    async function load(params: Record<string, string> = {}) {
        setLoading(true); setError(null);
        try {
            const [dealsRes, summaryRes] = await Promise.all([
                axios.get<ApiResponse>('/api/v1/pipeline/deals', { params: { per_page: 200, ...params } }),
                axios.get<PipelineSummary>('/api/v1/pipeline/deals/summary'),
            ]);
            setBoard(groupByStage(dealsRes.data.data));
            setSummary(summaryRes.data);
        } catch { setError('No se pudo cargar el pipeline.'); }
        finally { setLoading(false); }
    }

    async function refreshSummary() {
        const r = await axios.get<PipelineSummary>('/api/v1/pipeline/deals/summary');
        setSummary(r.data);
    }

    useEffect(() => { load(); axios.get('/api/v1/team/members').then(r => setAgents(r.data.data ?? r.data)); }, []);

    useEffect(() => {
        clearTimeout(filterRef.current);
        filterRef.current = setTimeout(() => {
            const p: Record<string, string> = {};
            if (search)      p.search      = search;
            if (agentFilter) p.assigned_to = agentFilter;
            if (dateFrom)    p.date_from   = dateFrom;
            if (dateTo)      p.date_to     = dateTo;
            if (valueMin)    p.value_min   = valueMin;
            if (valueMax)    p.value_max   = valueMax;
            load(p);
        }, 320);
    }, [search, agentFilter, dateFrom, dateTo, valueMin, valueMax]);

    // ── Drag & drop ────────────────────────────────────────────────────────────

    async function onDragEnd({ destination, source, draggableId }: DropResult) {
        if (!destination) return;
        const src = source.droppableId as DealStage;
        const dst = destination.droppableId as DealStage;
        if (src === dst && source.index === destination.index) return;

        const prev = structuredClone(board);
        const next = structuredClone(board);
        const [moved] = next[src].splice(source.index, 1);
        if (!moved) return;
        moved.stage = dst;
        next[dst].splice(destination.index, 0, moved);
        setBoard(next); setMovingId(draggableId);

        try {
            const r = await axios.patch<{ data: PipelineDeal }>(`/api/v1/pipeline/deals/${draggableId}/stage`, { stage: dst });
            setBoard(cur => {
                const b = structuredClone(cur);
                const i = b[dst].findIndex(d => d.id === r.data.data.id);
                if (i >= 0) b[dst][i] = { ...b[dst][i], ...r.data.data };
                return b;
            });
            refreshSummary();
        } catch { setBoard(prev); setError('No se pudo mover el deal.'); }
        finally { setMovingId(null); }
    }

    // ── Modal callbacks ────────────────────────────────────────────────────────

    function openCreate(stage: DealStage) { setCreateStage(stage); setEditDeal(null); setModalOpen(true); }
    function openEdit(deal: PipelineDeal) { setEditDeal(deal); setModalOpen(true); }

    function handleSaved(saved: PipelineDeal) {
        setBoard(cur => {
            const b = structuredClone(cur);
            if (editDeal) {
                // find & replace in whatever column it's in
                for (const s of Object.keys(b) as DealStage[]) {
                    const i = b[s].findIndex(d => d.id === saved.id);
                    if (i >= 0) { b[s][i] = saved; break; }
                }
            } else {
                b[saved.stage].unshift(saved);
            }
            return b;
        });
        setModalOpen(false);
        refreshSummary();
    }

    function handleDeleted(id: string) {
        setBoard(cur => {
            const b = structuredClone(cur);
            for (const s of Object.keys(b) as DealStage[]) b[s] = b[s].filter(d => d.id !== id);
            return b;
        });
        setModalOpen(false);
        refreshSummary();
    }

    // ── Render ─────────────────────────────────────────────────────────────────

    return (
        <AppLayout title="Pipeline">
            <Head title="Pipeline" />

            <div className="h-[calc(100vh-3.5rem)] flex flex-col bg-gray-50 overflow-hidden">

                {/* Top bar */}
                <div className="shrari-0 px-5 pt-4 pb-3 bg-white border-b border-gray-200">
                    <div className="flex items-center justify-between gap-4 mb-3">
                        <div>
                            <h1 className="text-lg font-bold text-gray-900">Pipeline</h1>
                            <p className="text-xs text-gray-400 mt-0.5">Arrastra las tarjetas para cambiar de etapa</p>
                        </div>
                    </div>
                    <div className="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-end">
                        {/* Search */}
                        <div className="relative w-full sm:w-auto">
                            <Search size={13} className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
                            <input value={search} onChange={e => setSearch(e.target.value)}
                                placeholder="Buscar deal…"
                                className="w-full sm:w-40 pl-8 pr-3 py-1.5 text-sm rounded-lg border border-gray-300 bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500" />
                        </div>
                        {/* Agent filter */}
                        <div className="relative w-full sm:w-auto">
                            <select value={agentFilter} onChange={e => setAgentFilter(e.target.value)}
                                className="w-full sm:w-auto appearance-none pl-3 pr-7 py-1.5 text-sm rounded-lg border border-gray-300 bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500">
                                <option value="">Todos los agentes</option>
                                <option value="me">Mis deals</option>
                                <option value="unassigned">Sin asignar</option>
                                {agents.map(a => <option key={a.id} value={a.id}>{a.name}</option>)}
                            </select>
                            <ChevronDown size={12} className="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none" />
                        </div>
                        {/* Date range */}
                        <input type="date" value={dateFrom} onChange={e => setDateFrom(e.target.value)}
                            title="Desde"
                            className="w-full sm:w-36 py-1.5 px-2 text-sm rounded-lg border border-gray-300 bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500" />
                        <span className="hidden sm:block text-xs text-gray-400">–</span>
                        <input type="date" value={dateTo} onChange={e => setDateTo(e.target.value)}
                            title="Hasta"
                            className="w-full sm:w-36 py-1.5 px-2 text-sm rounded-lg border border-gray-300 bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500" />
                        {/* Value range */}
                        <input type="number" min="0" value={valueMin} onChange={e => setValueMin(e.target.value)}
                            placeholder="Valor mín."
                            className="w-full sm:w-28 py-1.5 px-2 text-sm rounded-lg border border-gray-300 bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500" />
                        <input type="number" min="0" value={valueMax} onChange={e => setValueMax(e.target.value)}
                            placeholder="Valor máx."
                            className="w-full sm:w-28 py-1.5 px-2 text-sm rounded-lg border border-gray-300 bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500" />
                        {/* Clear filters */}
                        {(search || agentFilter || dateFrom || dateTo || valueMin || valueMax) && (
                            <button onClick={() => { setSearch(''); setAgentFilter(''); setDateFrom(''); setDateTo(''); setValueMin(''); setValueMax(''); }}
                                className="flex items-center justify-center gap-1 min-h-11 w-full sm:w-auto text-xs text-gray-400 hover:text-red-500 px-2 py-1.5 rounded-lg hover:bg-red-50 transition">
                                <X size={12} /> Limpiar
                            </button>
                        )}
                    </div>
                </div>

                <div className="flex-1 overflow-y-auto px-5 py-4 space-y-4">
                    {/* Summary */}
                    {summary && <SummaryBar summary={summary} />}
                    {/* Funnel */}
                    {summary && <FunnelSection summary={summary} open={funnelOpen} onToggle={() => setFunnelOpen(o => !o)} />}

                    {/* Error */}
                    {error && (
                        <div className="flex items-center gap-2 rounded-xl border border-rose-200 bg-rose-50 px-4 py-2.5 text-sm text-rose-700">
                            <AlertCircle size={15} />{error}
                        </div>
                    )}

                    {/* Board */}
                    {loading ? (
                        <div className="flex h-48 items-center justify-center">
                            <div className="h-7 w-7 animate-spin rounded-full border-2 border-emerald-500 border-t-transparent" />
                        </div>
                    ) : (
                        <DragDropContext onDragEnd={onDragEnd}>
                            <div className="flex gap-3 overflow-x-auto pb-4" style={{ minWidth: 'max-content' }}>
                                {STAGES.map(stage => {
                                    const deals = board[stage.key];
                                    const stageTotal = summary?.by_stage.find(s => s.stage === stage.key)?.total_value ?? 0;
                                    return (
                                        <div key={stage.key} className="w-[85vw] sm:w-[272px] shrink-0 flex flex-col rounded-2xl border border-gray-200 bg-white shadow-sm overflow-hidden">
                                            {/* Column header */}
                                            <div className={`px-3.5 py-3 border-b ${stage.header} flex items-center justify-between gap-2`}>
                                                <div className="flex items-center gap-2 min-w-0">
                                                    <span className={`w-2 h-2 rounded-full shrari-0 ${stage.dot}`} />
                                                    <span className={`text-sm font-semibold truncate ${stage.color}`}>{stage.label}</span>
                                                    <span className="text-xs text-gray-400 font-medium bg-white/70 rounded-full px-1.5 py-0.5 shrari-0">{deals.length}</span>
                                                </div>
                                                <button onClick={() => openCreate(stage.key)}
                                                    className="shrink-0 flex h-11 w-11 items-center justify-center rounded-lg text-gray-400 hover:text-emerald-600 hover:bg-white/80 transition"
                                                    title="Nuevo deal">
                                                    <Plus size={15} />
                                                </button>
                                            </div>
                                            {/* Value sub-header */}
                                            {stageTotal > 0 && (
                                                <div className="px-3.5 py-1 bg-gray-50 border-b border-gray-100">
                                                    <span className="text-[11px] text-gray-400 font-medium">{fmtValue(stageTotal)}</span>
                                                </div>
                                            )}

                                            {/* Droppable area */}
                                            <Droppable droppableId={stage.key}>
                                                {(provided, snapshot) => (
                                                    <div ref={provided.innerRef} {...provided.droppableProps}
                                                        className={`flex-1 min-h-[120px] p-2 space-y-2 transition-colors ${snapshot.isDraggingOver ? 'bg-emerald-50/60' : ''}`}>
                                                        {deals.map((deal, index) => (
                                                            <Draggable key={deal.id} draggableId={deal.id} index={index}>
                                                                {(dp, ds) => (
                                                                    <div ref={dp.innerRef} {...dp.draggableProps} {...dp.dragHandleProps}
                                                                        className={ds.isDragging ? 'rotate-1 scale-105' : ''}>
                                                                        <DealCard deal={deal} moving={movingId === deal.id} onEdit={() => openEdit(deal)} />
                                                                    </div>
                                                                )}
                                                            </Draggable>
                                                        ))}
                                                        {provided.placeholder}
                                                        {deals.length === 0 && !snapshot.isDraggingOver && (
                                                            <button onClick={() => openCreate(stage.key)}
                                                                className="w-full flex items-center justify-center gap-2 rounded-xl border border-dashed border-gray-200 py-5 text-xs text-gray-300 hover:border-emerald-400 hover:text-emerald-500 transition">
                                                                <Plus size={13} /> Agregar
                                                            </button>
                                                        )}
                                                    </div>
                                                )}
                                            </Droppable>
                                        </div>
                                    );
                                })}
                            </div>
                        </DragDropContext>
                    )}
                </div>
            </div>

            {modalOpen && (
                <DealModal
                    deal={editDeal}
                    agents={agents}
                    defaultStage={createStage}
                    onClose={() => setModalOpen(false)}
                    onSaved={handleSaved}
                    onDeleted={handleDeleted}
                />
            )}
        </AppLayout>
    );
}

import { Contact, Conversation, PipelineDeal, Task, User } from '@/types';
import { formatDistanceToNow } from 'date-fns';
import { es } from 'date-fns/locale';
import {
    AlertCircle,
    Briefcase,
    Building2,
    Check,
    CheckSquare,
    ChevronRight,
    Clock,
    Mail,
    Phone,
    Plus,
    Tag,
    Trophy,
    User as UserIcon,
    X,
} from 'lucide-react';
import axios from 'axios';
import { useEffect, useState } from 'react';
import ContactAvatar from './ContactAvatar';

// ─── Quick task form ──────────────────────────────────────────────────────────

function QuickTaskForm({
    contactId,
    conversationId,
    onCreated,
    onCancel,
}: {
    contactId: string;
    conversationId: string;
    onCreated: (t: Task) => void;
    onCancel: () => void;
}) {
    const [title, setTitle]   = useState('');
    const [saving, setSaving] = useState(false);

    async function submit(e: React.FormEvent) {
        e.preventDefault();
        if (!title.trim()) return;
        setSaving(true);
        try {
            const res = await axios.post<{ data: Task }>('/api/v1/tasks', {
                title: title.trim(),
                contact_id: contactId,
                conversation_id: conversationId,
            });
            onCreated(res.data.data);
            setTitle('');
        } finally {
            setSaving(false);
        }
    }

    return (
        <form onSubmit={submit} className="mt-2 flex gap-1.5">
            <input
                autoFocus
                value={title}
                onChange={(e) => setTitle(e.target.value)}
                placeholder="Título de la tarea…"
                className="min-w-0 flex-1 rounded-lg border border-gray-200 px-2.5 py-1.5 text-xs focus:border-ari-400 focus:outline-none"
            />
            <button
                type="submit"
                disabled={saving || !title.trim()}
                className="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-ari-600 text-white disabled:opacity-40"
            >
                <Check className="h-3.5 w-3.5" />
            </button>
            <button
                type="button"
                onClick={onCancel}
                className="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg border border-gray-200 text-gray-400 hover:bg-gray-50"
            >
                <X className="h-3.5 w-3.5" />
            </button>
        </form>
    );
}

interface Props {
    conversation: Conversation;
    onClose: () => void;
}

function InfoRow({ icon: Icon, label, value }: { icon: React.ElementType; label: string; value: string | null | undefined }) {
    if (!value) return null;
    return (
        <div className="flex items-start gap-3 py-2">
            <Icon className="mt-0.5 h-4 w-4 flex-shrari-0 text-gray-400" />
            <div className="min-w-0">
                <p className="text-[10px] font-medium uppercase tracking-wide text-gray-400">{label}</p>
                <p className="text-sm text-gray-900">{value}</p>
            </div>
        </div>
    );
}

const STAGE_LABELS: Record<string, string> = {
    lead: 'Lead', qualified: 'Calificado', proposal: 'Propuesta',
    negotiation: 'Negociación', closed_won: 'Ganado', closed_lost: 'Perdido',
};

function fmtValue(v: number, currency = 'COP') {
    if (v >= 1_000_000) return `${currency} ${(v / 1_000_000).toFixed(1)}M`;
    if (v >= 1_000)     return `${currency} ${(v / 1_000).toFixed(0)}K`;
    return `${currency} ${v.toLocaleString('es-CO')}`;
}

export default function ContactPanel({ conversation, onClose }: Props) {
    const contact  = conversation.contact as Contact | undefined;
    const assignee = conversation.assignee as User | undefined;

    const [deals, setDeals]               = useState<PipelineDeal[]>([]);
    const [dealsLoading, setDealsLoading] = useState(false);
    const [tasks, setTasks]               = useState<Task[]>([]);
    const [tasksLoading, setTasksLoading] = useState(false);
    const [addingTask, setAddingTask]     = useState(false);

    useEffect(() => {
        if (!contact?.id) { setDeals([]); return; }
        setDealsLoading(true);
        axios.get('/api/v1/pipeline/deals', { params: { contact_id: contact.id, per_page: 5 } })
            .then(r => setDeals(r.data.data ?? []))
            .finally(() => setDealsLoading(false));
    }, [contact?.id]);

    useEffect(() => {
        if (!contact?.id) { setTasks([]); return; }
        setTasksLoading(true);
        axios.get('/api/v1/tasks', { params: { contact_id: contact.id, status: 'pending', per_page: 5 } })
            .then(r => setTasks(r.data.data ?? []))
            .finally(() => setTasksLoading(false));
    }, [contact?.id]);

    async function completeTask(task: Task) {
        await axios.patch(`/api/v1/tasks/${task.id}/complete`);
        setTasks(prev => prev.filter(t => t.id !== task.id));
    }

    const displayName =
        contact?.name ?? contact?.push_name ?? contact?.phone ?? 'Desconocido';

    const dt1Seconds = conversation.first_message_at && conversation.first_response_at
        ? Math.abs(
              new Date(conversation.first_response_at).getTime() -
              new Date(conversation.first_message_at).getTime(),
          ) / 1000
        : null;

    function formatDt1(seconds: number): string {
        if (seconds < 60) return `${Math.round(seconds)}s`;
        if (seconds < 3600) return `${Math.round(seconds / 60)}m`;
        return `${(seconds / 3600).toFixed(1)}h`;
    }

    const statusLabel: Record<string, string> = {
        open:    'Abierta',
        pending: 'Pendiente',
        closed:  'Cerrada',
    };

    const statusColor: Record<string, string> = {
        open:    'bg-green-100 text-green-700',
        pending: 'bg-yellow-100 text-yellow-700',
        closed:  'bg-gray-100 text-gray-600',
    };

    return (
        <aside className="flex h-full w-full flex-shrink-0 flex-col overflow-hidden border-l border-gray-200 bg-white">
            {/* Header */}
            <div className="flex items-center justify-between border-b border-gray-100 px-4 py-3">
                <span className="text-sm font-semibold text-gray-800">Información</span>
                <button onClick={onClose} className="rounded-lg p-1 text-gray-400 hover:bg-gray-100">
                    <X className="h-4 w-4" />
                </button>
            </div>

            <div className="flex-1 overflow-y-auto">
                {/* Contact overview */}
                <div className="flex flex-col items-center gap-2 border-b border-gray-100 px-4 py-5">
                    <ContactAvatar
                        name={displayName}
                        imageUrl={contact?.profile_pic_url}
                        sizeClass="h-16 w-16"
                    />
                    <div className="text-center">
                        <p className="font-semibold text-gray-900">{displayName}</p>
                        {contact?.push_name && contact.name && contact.push_name !== contact.name && (
                            <p className="text-xs text-gray-400">{contact.push_name}</p>
                        )}
                    </div>
                </div>

                {/* Contact details */}
                <div className="border-b border-gray-100 px-4 py-2">
                    <p className="mb-1 text-[10px] font-semibold uppercase tracking-widest text-gray-400">Contacto</p>
                    <InfoRow icon={Phone} label="Teléfono" value={contact?.phone} />
                    <InfoRow icon={Mail} label="Email" value={contact?.email} />
                    <InfoRow icon={Building2} label="Empresa" value={contact?.company} />
                    {contact?.tags && contact.tags.length > 0 && (
                        <div className="flex items-start gap-3 py-2">
                            <Tag className="mt-0.5 h-4 w-4 flex-shrari-0 text-gray-400" />
                            <div>
                                <p className="text-[10px] font-medium uppercase tracking-wide text-gray-400">Etiquetas</p>
                                <div className="mt-1 flex flex-wrap gap-1">
                                    {contact.tags.map((tag) => (
                                        <span
                                            key={tag}
                                            className="rounded-full bg-ari-50 px-2 py-0.5 text-xs font-medium text-ari-700"
                                        >
                                            {tag}
                                        </span>
                                    ))}
                                </div>
                            </div>
                        </div>
                    )}
                    {contact?.notes && (
                        <div className="mt-2 rounded-lg bg-yellow-50 px-3 py-2 text-xs text-gray-700">
                            {contact.notes}
                        </div>
                    )}
                </div>

                {/* Conversation details */}
                <div className="border-b border-gray-100 px-4 py-2">
                    <p className="mb-1 text-[10px] font-semibold uppercase tracking-widest text-gray-400">Conversación</p>

                    <div className="flex items-center gap-2 py-2">
                        <span
                            className={`rounded-full px-2 py-0.5 text-xs font-medium ${statusColor[conversation.status] ?? 'bg-gray-100 text-gray-600'}`}
                        >
                            {statusLabel[conversation.status] ?? conversation.status}
                        </span>
                    </div>

                    {assignee && (
                        <div className="flex items-start gap-3 py-2">
                            <UserIcon className="mt-0.5 h-4 w-4 flex-shrari-0 text-gray-400" />
                            <div>
                                <p className="text-[10px] font-medium uppercase tracking-wide text-gray-400">Asignado</p>
                                <p className="text-sm text-gray-900">{assignee.name}</p>
                            </div>
                        </div>
                    )}

                    {dt1Seconds !== null && (
                        <div className="flex items-start gap-3 py-2">
                            <Clock className="mt-0.5 h-4 w-4 flex-shrari-0 text-gray-400" />
                            <div>
                                <p className="text-[10px] font-medium uppercase tracking-wide text-gray-400">Tiempo de respuesta (Dt1)</p>
                                <p className="text-sm font-semibold text-ari-600">{formatDt1(dt1Seconds)}</p>
                            </div>
                        </div>
                    )}

                    {conversation.first_message_at && (
                        <div className="py-1 text-xs text-gray-400">
                            Primera vez:{' '}
                            {formatDistanceToNow(new Date(conversation.first_message_at), {
                                addSuffix: true,
                                locale: es,
                            })}
                        </div>
                    )}
                </div>

                {/* Activity */}
                <div className="border-b border-gray-100 px-4 py-2">
                    <p className="mb-1 text-[10px] font-semibold uppercase tracking-widest text-gray-400">Actividad</p>
                    <div className="text-xs text-gray-500">
                        <p>{conversation.message_count} mensajes en esta conversación</p>
                        {contact?.last_contact_at && (
                            <p className="mt-1">
                                Último contacto:{' '}
                                {formatDistanceToNow(new Date(contact.last_contact_at), {
                                    addSuffix: true,
                                    locale: es,
                                })}
                            </p>
                        )}
                    </div>
                </div>

                {/* Tasks */}
                {contact?.id && (
                    <div className="border-b border-gray-100 px-4 py-2">
                        <div className="mb-2 flex items-center justify-between">
                            <p className="text-[10px] font-semibold uppercase tracking-widest text-gray-400 flex items-center gap-1">
                                <CheckSquare size={10} /> Tareas pendientes
                            </p>
                            <button
                                onClick={() => setAddingTask(true)}
                                className="flex items-center gap-0.5 rounded p-0.5 text-gray-400 hover:bg-gray-100 hover:text-ari-600"
                                title="Crear tarea"
                            >
                                <Plus size={13} />
                            </button>
                        </div>

                        {tasksLoading && <p className="text-xs text-gray-400">Cargando…</p>}

                        {!tasksLoading && tasks.length === 0 && !addingTask && (
                            <p className="text-xs text-gray-400">Sin tareas pendientes</p>
                        )}

                        {!tasksLoading && tasks.map(task => (
                            <div key={task.id} className="mb-1.5 flex items-start gap-2">
                                <button
                                    onClick={() => completeTask(task)}
                                    className="mt-0.5 flex h-4 w-4 shrink-0 items-center justify-center rounded-full border-2 border-gray-300 hover:border-ari-400"
                                />
                                <div className="min-w-0 flex-1">
                                    <p className="text-xs text-gray-800 line-clamp-1">{task.title}</p>
                                    {task.due_at && (
                                        <p className={`text-[10px] ${task.is_overdue ? 'text-red-500' : 'text-gray-400'}`}>
                                            {new Date(task.due_at).toLocaleString('es-CO', {
                                                month: 'short', day: 'numeric',
                                                hour: '2-digit', minute: '2-digit',
                                            })}
                                        </p>
                                    )}
                                </div>
                            </div>
                        ))}

                        {addingTask && (
                            <QuickTaskForm
                                contactId={contact.id}
                                conversationId={conversation.id}
                                onCreated={(t) => { setTasks(prev => [t, ...prev]); setAddingTask(false); }}
                                onCancel={() => setAddingTask(false)}
                            />
                        )}
                    </div>
                )}

                {/* Deals */}
                <div className="px-4 py-2">
                    <p className="mb-2 text-[10px] font-semibold uppercase tracking-widest text-gray-400 flex items-center gap-1">
                        <Briefcase size={10} /> Deals
                    </p>
                    {dealsLoading && <p className="text-xs text-gray-400">Cargando…</p>}
                    {!dealsLoading && deals.length === 0 && (
                        <p className="text-xs text-gray-400">Sin deals asociados</p>
                    )}
                    {!dealsLoading && deals.map(deal => (
                        <div key={deal.id} className="mb-2 rounded-lg border border-gray-100 bg-gray-50 px-3 py-2">
                            <div className="flex items-start justify-between gap-1">
                                <p className="text-xs font-medium text-gray-800 line-clamp-1 flex-1">{deal.title}</p>
                                {deal.stage === 'closed_won'  && <Trophy size={11} className="text-emerald-500 shrari-0 mt-0.5" />}
                                {deal.stage === 'closed_lost' && <AlertCircle size={11} className="text-rose-400 shrari-0 mt-0.5" />}
                            </div>
                            <div className="mt-1 flex items-center gap-2 text-[11px] text-gray-400">
                                <span>{STAGE_LABELS[deal.stage] ?? deal.stage}</span>
                                {deal.value && (
                                    <span className="font-semibold text-gray-600">{fmtValue(Number(deal.value), deal.currency)}</span>
                                )}
                            </div>
                        </div>
                    ))}
                </div>
            </div>

            {/* Footer link */}
            <div className="border-t border-gray-100 px-4 py-3">
                <button className="flex w-full items-center justify-between text-xs text-ari-600 hover:text-ari-700">
                    <span>Ver perfil completo</span>
                    <ChevronRight className="h-3.5 w-3.5" />
                </button>
            </div>
        </aside>
    );
}

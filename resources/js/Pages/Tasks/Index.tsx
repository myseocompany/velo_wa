import AppLayout from '@/Layouts/AppLayout';
import { PageProps, PaginatedData, Task, User } from '@/types';
import { usePage } from '@inertiajs/react';
import axios from 'axios';
import {
    AlertCircle,
    Calendar,
    Check,
    ChevronLeft,
    ChevronRight,
    Circle,
    Plus,
    RotateCcw,
    User as UserIcon,
    X,
} from 'lucide-react';
import { useEffect, useState } from 'react';

// ─── Priority helpers ─────────────────────────────────────────────────────────

const PRIORITY_LABEL: Record<string, string> = {
    low: 'Baja',
    medium: 'Media',
    high: 'Alta',
};

const PRIORITY_CLASS: Record<string, string> = {
    low: 'bg-gray-100 text-gray-600',
    medium: 'bg-amber-100 text-amber-700',
    high: 'bg-red-100 text-red-700',
};

// ─── Tab type ─────────────────────────────────────────────────────────────────

type TabKey = 'pending' | 'today' | 'overdue' | 'completed';

const TABS: { key: TabKey; label: string }[] = [
    { key: 'pending', label: 'Pendientes' },
    { key: 'today', label: 'Hoy' },
    { key: 'overdue', label: 'Vencidas' },
    { key: 'completed', label: 'Completadas' },
];

// ─── Task form state ──────────────────────────────────────────────────────────

interface TaskFormState {
    title: string;
    description: string;
    due_at: string;
    priority: string;
    assigned_to: string;
    contact_id: string;
}

const EMPTY_FORM: TaskFormState = {
    title: '',
    description: '',
    due_at: '',
    priority: 'medium',
    assigned_to: '',
    contact_id: '',
};

// ─── Task modal ───────────────────────────────────────────────────────────────

interface TaskModalProps {
    task?: Task | null;
    agents: User[];
    onClose: () => void;
    onSaved: (task: Task) => void;
}

function TaskModal({ task, agents, onClose, onSaved }: TaskModalProps) {
    const [form, setForm] = useState<TaskFormState>(
        task
            ? {
                  title: task.title,
                  description: task.description ?? '',
                  due_at: task.due_at ? task.due_at.slice(0, 16) : '',
                  priority: task.priority,
                  assigned_to: task.assigned_to ?? '',
                  contact_id: task.contact_id ?? '',
              }
            : EMPTY_FORM,
    );
    const [saving, setSaving] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});

    const set = (field: keyof TaskFormState, value: string) =>
        setForm((prev) => ({ ...prev, [field]: value }));

    async function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        setSaving(true);
        setErrors({});

        const payload = {
            title: form.title.trim(),
            description: form.description.trim() || null,
            due_at: form.due_at || null,
            priority: form.priority,
            assigned_to: form.assigned_to || null,
            contact_id: form.contact_id || null,
        };

        try {
            let res: { data: { data: Task } };
            if (task) {
                res = await axios.put(`/api/v1/tasks/${task.id}`, payload);
            } else {
                res = await axios.post('/api/v1/tasks', payload);
            }
            onSaved(res.data.data);
        } catch (err: unknown) {
            if (axios.isAxiosError(err) && err.response?.data?.errors) {
                const errs: Record<string, string> = {};
                for (const [k, v] of Object.entries(err.response.data.errors)) {
                    errs[k] = Array.isArray(v) ? (v as string[])[0] : String(v);
                }
                setErrors(errs);
            }
        } finally {
            setSaving(false);
        }
    }

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div className="w-full max-w-lg overflow-hidden rounded-2xl bg-white shadow-xl">
                {/* Header */}
                <div className="flex items-center justify-between border-b border-gray-100 px-5 py-4">
                    <h3 className="font-semibold text-gray-900">
                        {task ? 'Editar tarea' : 'Nueva tarea'}
                    </h3>
                    <button onClick={onClose} className="text-gray-400 hover:text-gray-600">
                        <X className="h-5 w-5" />
                    </button>
                </div>

                <form onSubmit={handleSubmit} className="space-y-4 px-5 py-4">
                    {/* Title */}
                    <div>
                        <label className="mb-1 block text-sm font-medium text-gray-700">
                            Título <span className="text-red-500">*</span>
                        </label>
                        <input
                            value={form.title}
                            onChange={(e) => set('title', e.target.value)}
                            placeholder="Ej: Llamar al cliente para confirmar"
                            className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-ari-500 focus:outline-none"
                        />
                        {errors.title && (
                            <p className="mt-1 text-xs text-red-500">{errors.title}</p>
                        )}
                    </div>

                    {/* Description */}
                    <div>
                        <label className="mb-1 block text-sm font-medium text-gray-700">
                            Descripción
                        </label>
                        <textarea
                            value={form.description}
                            onChange={(e) => set('description', e.target.value)}
                            rows={3}
                            placeholder="Detalles adicionales..."
                            className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-ari-500 focus:outline-none"
                        />
                    </div>

                    {/* Due date + Priority row */}
                    <div className="grid grid-cols-2 gap-3">
                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">
                                Fecha y hora
                            </label>
                            <input
                                type="datetime-local"
                                value={form.due_at}
                                onChange={(e) => set('due_at', e.target.value)}
                                className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-ari-500 focus:outline-none"
                            />
                            {errors.due_at && (
                                <p className="mt-1 text-xs text-red-500">{errors.due_at}</p>
                            )}
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">
                                Prioridad
                            </label>
                            <select
                                value={form.priority}
                                onChange={(e) => set('priority', e.target.value)}
                                className="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm focus:border-ari-500 focus:outline-none"
                            >
                                <option value="low">Baja</option>
                                <option value="medium">Media</option>
                                <option value="high">Alta</option>
                            </select>
                        </div>
                    </div>

                    {/* Assigned to */}
                    <div>
                        <label className="mb-1 block text-sm font-medium text-gray-700">
                            Asignar a
                        </label>
                        <select
                            value={form.assigned_to}
                            onChange={(e) => set('assigned_to', e.target.value)}
                            className="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm focus:border-ari-500 focus:outline-none"
                        >
                            <option value="">Sin asignar</option>
                            {agents.map((a) => (
                                <option key={a.id} value={a.id}>
                                    {a.name}
                                </option>
                            ))}
                        </select>
                        {errors.assigned_to && (
                            <p className="mt-1 text-xs text-red-500">{errors.assigned_to}</p>
                        )}
                    </div>

                    {/* Actions */}
                    <div className="flex justify-end gap-2 border-t border-gray-100 pt-4">
                        <button
                            type="button"
                            onClick={onClose}
                            className="rounded-lg px-4 py-2 text-sm text-gray-600 hover:bg-gray-100"
                        >
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            disabled={saving}
                            className="rounded-lg bg-ari-600 px-4 py-2 text-sm font-medium text-white hover:bg-ari-700 disabled:opacity-50"
                        >
                            {saving ? 'Guardando...' : task ? 'Guardar cambios' : 'Crear tarea'}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}

// ─── Task row ─────────────────────────────────────────────────────────────────

interface TaskRowProps {
    task: Task;
    onComplete: (task: Task) => void;
    onReopen: (task: Task) => void;
    onEdit: (task: Task) => void;
    onDelete: (task: Task) => void;
}

function TaskRow({ task, onComplete, onReopen, onEdit, onDelete }: TaskRowProps) {
    const isCompleted = task.completed_at !== null;

    return (
        <tr className="group hover:bg-gray-50">
            {/* Checkbox-style complete button */}
            <td className="w-10 px-4 py-3">
                <button
                    onClick={() => (isCompleted ? onReopen(task) : onComplete(task))}
                    className={`flex h-5 w-5 items-center justify-center rounded-full border-2 transition-colors ${
                        isCompleted
                            ? 'border-ari-500 bg-ari-500 text-white'
                            : 'border-gray-300 hover:border-ari-400'
                    }`}
                    title={isCompleted ? 'Reabrir' : 'Completar'}
                >
                    {isCompleted ? (
                        <Check className="h-3 w-3" />
                    ) : (
                        <Circle className="h-3 w-3 opacity-0 group-hover:opacity-30" />
                    )}
                </button>
            </td>

            {/* Title + description */}
            <td className="px-2 py-3">
                <button
                    onClick={() => onEdit(task)}
                    className={`text-left text-sm font-medium ${isCompleted ? 'text-gray-400 line-through' : 'text-gray-900 hover:text-ari-600'}`}
                >
                    {task.title}
                </button>
                {task.contact && (
                    <p className="mt-0.5 text-xs text-gray-400">{task.contact.display_name}</p>
                )}
            </td>

            {/* Priority */}
            <td className="hidden px-2 py-3 sm:table-cell">
                <span
                    className={`inline-flex rounded-full px-2 py-0.5 text-xs font-medium ${PRIORITY_CLASS[task.priority] ?? ''}`}
                >
                    {PRIORITY_LABEL[task.priority] ?? task.priority}
                </span>
            </td>

            {/* Due date */}
            <td className="hidden px-2 py-3 md:table-cell">
                {task.due_at ? (
                    <span
                        className={`flex items-center gap-1 text-xs ${
                            task.is_overdue && !isCompleted ? 'font-medium text-red-600' : 'text-gray-500'
                        }`}
                    >
                        {task.is_overdue && !isCompleted && (
                            <AlertCircle className="h-3 w-3" />
                        )}
                        <Calendar className="h-3 w-3 opacity-60" />
                        {new Date(task.due_at).toLocaleString('es-CO', {
                            month: 'short',
                            day: 'numeric',
                            hour: '2-digit',
                            minute: '2-digit',
                        })}
                    </span>
                ) : (
                    <span className="text-xs text-gray-300">—</span>
                )}
            </td>

            {/* Assignee */}
            <td className="hidden px-2 py-3 lg:table-cell">
                {task.assignee ? (
                    <span className="flex items-center gap-1.5 text-xs text-gray-600">
                        <div className="flex h-5 w-5 items-center justify-center rounded-full bg-ari-100 text-xs font-semibold text-ari-700">
                            {task.assignee.name.charAt(0).toUpperCase()}
                        </div>
                        {task.assignee.name}
                    </span>
                ) : (
                    <span className="flex items-center gap-1 text-xs text-gray-300">
                        <UserIcon className="h-3 w-3" />
                        Sin asignar
                    </span>
                )}
            </td>

            {/* Actions */}
            <td className="px-4 py-3 text-right">
                <div className="flex items-center justify-end gap-1 opacity-0 group-hover:opacity-100">
                    {isCompleted ? (
                        <button
                            onClick={() => onReopen(task)}
                            title="Reabrir"
                            className="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600"
                        >
                            <RotateCcw className="h-3.5 w-3.5" />
                        </button>
                    ) : (
                        <button
                            onClick={() => onEdit(task)}
                            title="Editar"
                            className="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600"
                        >
                            <Calendar className="h-3.5 w-3.5" />
                        </button>
                    )}
                    <button
                        onClick={() => onDelete(task)}
                        title="Eliminar"
                        className="rounded p-1 text-gray-400 hover:bg-red-50 hover:text-red-500"
                    >
                        <X className="h-3.5 w-3.5" />
                    </button>
                </div>
            </td>
        </tr>
    );
}

// ─── Main page ────────────────────────────────────────────────────────────────

export default function TasksIndex() {
    const { auth } = usePage<PageProps>().props;
    const user = auth.user;

    const [tab, setTab] = useState<TabKey>('pending');
    const [tasks, setTasks] = useState<Task[]>([]);
    const [loading, setLoading] = useState(true);
    const [page, setPage] = useState(1);
    const [lastPage, setLastPage] = useState(1);
    const [total, setTotal] = useState(0);
    const [agentFilter, setAgentFilter] = useState('');
    const [agents, setAgents] = useState<User[]>([]);
    const [showModal, setShowModal] = useState(false);
    const [editingTask, setEditingTask] = useState<Task | null>(null);

    const canManage = user.role !== 'agent';

    // Load team members for agent filter + assignment
    useEffect(() => {
        axios.get<{ data: User[] }>('/api/v1/team/members').then((res) => {
            setAgents(res.data.data);
        });
    }, []);

    // Load tasks
    useEffect(() => {
        let cancelled = false;
        setLoading(true);

        const params: Record<string, unknown> = {
            status: tab,
            per_page: 25,
            page,
        };
        if (agentFilter) params.assigned_to = agentFilter;

        axios
            .get<PaginatedData<Task>>('/api/v1/tasks', { params })
            .then((res) => {
                if (!cancelled) {
                    setTasks(res.data.data);
                    setTotal(res.data.meta.total);
                    setLastPage(res.data.meta.last_page);
                }
            })
            .finally(() => {
                if (!cancelled) setLoading(false);
            });

        return () => {
            cancelled = true;
        };
    }, [tab, page, agentFilter]);

    // Reset page when tab/filter changes
    useEffect(() => {
        setPage(1);
    }, [tab, agentFilter]);

    function openCreate() {
        setEditingTask(null);
        setShowModal(true);
    }

    function openEdit(task: Task) {
        setEditingTask(task);
        setShowModal(true);
    }

    function handleSaved(saved: Task) {
        setShowModal(false);
        setEditingTask(null);
        // Refresh list
        setPage(1);
        setTasks((prev) => {
            const idx = prev.findIndex((t) => t.id === saved.id);
            if (idx >= 0) {
                const next = [...prev];
                next[idx] = saved;
                return next;
            }
            return [saved, ...prev];
        });
    }

    async function handleComplete(task: Task) {
        const res = await axios.patch<{ data: Task }>(`/api/v1/tasks/${task.id}/complete`);
        setTasks((prev) => prev.map((t) => (t.id === task.id ? res.data.data : t)));
        // Remove from pending/today/overdue tabs after a brief moment
        if (tab !== 'completed') {
            setTimeout(() => {
                setTasks((prev) => prev.filter((t) => t.id !== task.id));
                setTotal((n) => Math.max(0, n - 1));
            }, 600);
        }
    }

    async function handleReopen(task: Task) {
        const res = await axios.patch<{ data: Task }>(`/api/v1/tasks/${task.id}/reopen`);
        setTasks((prev) => prev.map((t) => (t.id === task.id ? res.data.data : t)));
        if (tab === 'completed') {
            setTimeout(() => {
                setTasks((prev) => prev.filter((t) => t.id !== task.id));
                setTotal((n) => Math.max(0, n - 1));
            }, 600);
        }
    }

    async function handleDelete(task: Task) {
        if (!window.confirm(`¿Eliminar la tarea "${task.title}"?`)) return;
        await axios.delete(`/api/v1/tasks/${task.id}`);
        setTasks((prev) => prev.filter((t) => t.id !== task.id));
        setTotal((n) => Math.max(0, n - 1));
    }

    return (
        <AppLayout title="Tareas">
            <div className="space-y-4 p-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-xl font-semibold text-gray-900">Tareas</h1>
                        <p className="text-sm text-gray-500">
                            {total} {total === 1 ? 'tarea' : 'tareas'}
                        </p>
                    </div>
                    <button
                        onClick={openCreate}
                        className="flex items-center gap-2 rounded-lg bg-ari-600 px-4 py-2 text-sm font-medium text-white hover:bg-ari-700"
                    >
                        <Plus className="h-4 w-4" />
                        Nueva tarea
                    </button>
                </div>

                {/* Tabs + filter */}
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex gap-1 rounded-lg bg-gray-100 p-1">
                        {TABS.map(({ key, label }) => (
                            <button
                                key={key}
                                onClick={() => setTab(key)}
                                className={`rounded-md px-3 py-1.5 text-sm font-medium transition-colors ${
                                    tab === key
                                        ? 'bg-white text-ari-700 shadow-sm'
                                        : 'text-gray-500 hover:text-gray-700'
                                }`}
                            >
                                {label}
                            </button>
                        ))}
                    </div>

                    {canManage && (
                        <select
                            value={agentFilter}
                            onChange={(e) => setAgentFilter(e.target.value)}
                            className="rounded-lg border border-gray-200 bg-white py-2 pl-3 pr-8 text-sm text-gray-700 focus:border-ari-400 focus:outline-none"
                        >
                            <option value="">Todos los agentes</option>
                            {agents.map((a) => (
                                <option key={a.id} value={a.id}>
                                    {a.name}
                                </option>
                            ))}
                        </select>
                    )}
                </div>

                {/* Table */}
                <div className="overflow-hidden rounded-xl border border-gray-200 bg-white">
                    {loading ? (
                        <div className="flex items-center justify-center py-16 text-sm text-gray-400">
                            Cargando tareas...
                        </div>
                    ) : tasks.length === 0 ? (
                        <div className="flex flex-col items-center justify-center py-16 text-sm text-gray-400">
                            <CheckSquareIcon className="mb-2 h-8 w-8 opacity-30" />
                            No hay tareas en esta categoría
                        </div>
                    ) : (
                        <table className="min-w-full divide-y divide-gray-100">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="w-10 px-4 py-3" />
                                    <th className="px-2 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                        Tarea
                                    </th>
                                    <th className="hidden px-2 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 sm:table-cell">
                                        Prioridad
                                    </th>
                                    <th className="hidden px-2 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 md:table-cell">
                                        Vence
                                    </th>
                                    <th className="hidden px-2 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 lg:table-cell">
                                        Asignado a
                                    </th>
                                    <th className="px-4 py-3" />
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-50">
                                {tasks.map((task) => (
                                    <TaskRow
                                        key={task.id}
                                        task={task}
                                        onComplete={handleComplete}
                                        onReopen={handleReopen}
                                        onEdit={openEdit}
                                        onDelete={handleDelete}
                                    />
                                ))}
                            </tbody>
                        </table>
                    )}

                    {/* Pagination */}
                    {lastPage > 1 && (
                        <div className="flex items-center justify-between border-t border-gray-100 px-4 py-3">
                            <p className="text-xs text-gray-500">
                                Página {page} de {lastPage} · {total} tareas
                            </p>
                            <div className="flex gap-1">
                                <button
                                    onClick={() => setPage((p) => Math.max(1, p - 1))}
                                    disabled={page === 1}
                                    className="rounded-lg p-1.5 text-gray-500 hover:bg-gray-100 disabled:opacity-30"
                                >
                                    <ChevronLeft className="h-4 w-4" />
                                </button>
                                <button
                                    onClick={() => setPage((p) => Math.min(lastPage, p + 1))}
                                    disabled={page === lastPage}
                                    className="rounded-lg p-1.5 text-gray-500 hover:bg-gray-100 disabled:opacity-30"
                                >
                                    <ChevronRight className="h-4 w-4" />
                                </button>
                            </div>
                        </div>
                    )}
                </div>
            </div>

            {/* Modal */}
            {showModal && (
                <TaskModal
                    task={editingTask}
                    agents={agents}
                    onClose={() => {
                        setShowModal(false);
                        setEditingTask(null);
                    }}
                    onSaved={handleSaved}
                />
            )}
        </AppLayout>
    );
}

// Inline icon to avoid import issues
function CheckSquareIcon({ className }: { className?: string }) {
    return (
        <svg
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth={2}
            strokeLinecap="round"
            strokeLinejoin="round"
            className={className}
        >
            <polyline points="9 11 12 14 22 4" />
            <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11" />
        </svg>
    );
}

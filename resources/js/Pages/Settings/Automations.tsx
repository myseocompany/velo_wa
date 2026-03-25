import AppLayout from '@/Layouts/AppLayout';
import { Head } from '@inertiajs/react';
import axios from 'axios';
import {
    Bot,
    ChevronDown,
    ChevronUp,
    GripVertical,
    History,
    Loader2,
    Pencil,
    Play,
    Plus,
    ToggleLeft,
    ToggleRight,
    Trash2,
    X,
} from 'lucide-react';
import { useEffect, useState } from 'react';

// ─── Types ────────────────────────────────────────────────────────────────────

type TriggerType = 'new_conversation' | 'keyword' | 'outside_hours' | 'no_response_timeout';
type ActionType = 'send_message' | 'assign_agent' | 'add_tag' | 'move_stage';
type DealStage = 'lead' | 'qualified' | 'proposal' | 'negotiation' | 'closed_won' | 'closed_lost';

interface TriggerConfig {
    keywords?: string[];
    match_type?: 'any' | 'all';
    case_insensitive?: boolean;
    minutes?: number;
}

interface ActionConfig {
    message?: string;
    agent_id?: string;
    tags?: string[];
    stage?: DealStage;
}

interface Automation {
    id: string;
    name: string;
    trigger_type: TriggerType;
    trigger_config: TriggerConfig;
    action_type: ActionType;
    action_config: ActionConfig;
    is_active: boolean;
    priority: number;
    execution_count: number;
    created_at: string;
    updated_at: string;
}

interface Agent {
    id: string;
    name: string;
    email: string;
}

interface AutomationLog {
    id: string;
    automation_id: string;
    conversation_id: string | null;
    trigger_type: TriggerType;
    action_type: ActionType;
    status: 'success' | 'failed';
    error_message: string | null;
    triggered_at: string;
}

// ─── Labels & colours ─────────────────────────────────────────────────────────

const TRIGGER_LABELS: Record<TriggerType, string> = {
    new_conversation: 'Nueva conversación',
    keyword: 'Palabra clave',
    outside_hours: 'Fuera de horario',
    no_response_timeout: 'Sin respuesta',
};

const TRIGGER_COLORS: Record<TriggerType, string> = {
    new_conversation: 'bg-blue-50 text-blue-700 border-blue-200',
    keyword: 'bg-violet-50 text-violet-700 border-violet-200',
    outside_hours: 'bg-amber-50 text-amber-700 border-amber-200',
    no_response_timeout: 'bg-rose-50 text-rose-700 border-rose-200',
};

const TRIGGER_DESCRIPTIONS: Record<TriggerType, string> = {
    new_conversation: 'Se dispara cuando se crea una conversación nueva.',
    keyword: 'Se dispara cuando el mensaje contiene ciertas palabras clave.',
    outside_hours: 'Se dispara cuando el mensaje llega fuera del horario del tenant.',
    no_response_timeout: 'Se dispara cuando no hay respuesta del agente en N minutos.',
};

const ACTION_LABELS: Record<ActionType, string> = {
    send_message: 'Enviar mensaje',
    assign_agent: 'Asignar agente',
    add_tag: 'Agregar etiqueta',
    move_stage: 'Mover etapa',
};

const ACTION_COLORS: Record<ActionType, string> = {
    send_message: 'bg-emerald-50 text-emerald-700 border-emerald-200',
    assign_agent: 'bg-sky-50 text-sky-700 border-sky-200',
    add_tag: 'bg-orange-50 text-orange-700 border-orange-200',
    move_stage: 'bg-purple-50 text-purple-700 border-purple-200',
};

const DEAL_STAGES: { value: DealStage; label: string }[] = [
    { value: 'lead', label: 'Lead' },
    { value: 'qualified', label: 'Calificado' },
    { value: 'proposal', label: 'Propuesta' },
    { value: 'negotiation', label: 'Negociación' },
    { value: 'closed_won', label: 'Ganado' },
    { value: 'closed_lost', label: 'Perdido' },
];

// ─── Logs drawer ──────────────────────────────────────────────────────────────

interface LogsDrawerProps {
    automation: Automation;
    onClose: () => void;
}

function LogsDrawer({ automation, onClose }: LogsDrawerProps) {
    const [logs, setLogs] = useState<AutomationLog[]>([]);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        axios
            .get<{ data: AutomationLog[] }>(`/api/v1/automations/${automation.id}/logs`)
            .then((res) => setLogs(res.data.data))
            .finally(() => setLoading(false));
    }, [automation.id]);

    function fmt(iso: string) {
        return new Date(iso).toLocaleString('es-CO', {
            dateStyle: 'short',
            timeStyle: 'short',
        });
    }

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-end bg-black/40">
            <div className="flex h-full w-full max-w-md flex-col bg-white shadow-2xl">
                {/* Header */}
                <div className="flex items-center justify-between border-b border-gray-200 px-5 py-4">
                    <div>
                        <h2 className="text-base font-semibold text-gray-900">
                            Historial de ejecuciones
                        </h2>
                        <p className="mt-0.5 text-xs text-gray-400 truncate max-w-xs">
                            {automation.name}
                        </p>
                    </div>
                    <button
                        onClick={onClose}
                        className="flex h-11 w-11 items-center justify-center rounded-lg text-gray-400 hover:bg-gray-100 hover:text-gray-600"
                    >
                        <X className="h-5 w-5" />
                    </button>
                </div>

                {/* Body */}
                <div className="flex-1 overflow-y-auto px-5 py-4">
                    {loading ? (
                        <div className="flex items-center justify-center gap-2 py-12 text-sm text-gray-400">
                            <Loader2 className="h-4 w-4 animate-spin" />
                            Cargando logs…
                        </div>
                    ) : logs.length === 0 ? (
                        <div className="flex flex-col items-center justify-center gap-2 py-12 text-center">
                            <History className="h-8 w-8 text-gray-200" />
                            <p className="text-sm text-gray-400">
                                Esta automatización aún no se ha ejecutado.
                            </p>
                        </div>
                    ) : (
                        <ul className="space-y-2">
                            {logs.map((log) => (
                                <li
                                    key={log.id}
                                    className={`rounded-lg border px-4 py-3 text-sm ${
                                        log.status === 'success'
                                            ? 'border-emerald-100 bg-emerald-50'
                                            : 'border-red-100 bg-red-50'
                                    }`}
                                >
                                    <div className="flex flex-col items-start gap-2 sm:flex-row sm:items-center sm:justify-between">
                                        <span
                                            className={`inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ${
                                                log.status === 'success'
                                                    ? 'bg-emerald-100 text-emerald-700'
                                                    : 'bg-red-100 text-red-700'
                                            }`}
                                        >
                                            {log.status === 'success' ? 'Exitoso' : 'Error'}
                                        </span>
                                        <span className="text-xs text-gray-400">
                                            {fmt(log.triggered_at)}
                                        </span>
                                    </div>
                                    <div className="mt-1.5 flex flex-wrap gap-x-3 gap-y-0.5 text-xs text-gray-500">
                                        <span>
                                            Disparador:{' '}
                                            <strong>{TRIGGER_LABELS[log.trigger_type]}</strong>
                                        </span>
                                        <span>
                                            Acción:{' '}
                                            <strong>{ACTION_LABELS[log.action_type]}</strong>
                                        </span>
                                        {log.conversation_id && (
                                            <span className="text-gray-400">
                                                conv: {log.conversation_id.slice(0, 8)}…
                                            </span>
                                        )}
                                    </div>
                                    {log.error_message && (
                                        <p className="mt-1.5 rounded bg-red-100 px-2 py-1 text-xs text-red-700">
                                            {log.error_message}
                                        </p>
                                    )}
                                </li>
                            ))}
                        </ul>
                    )}
                </div>

                <div className="border-t border-gray-100 px-5 py-3 text-xs text-gray-400">
                    Mostrando las últimas 50 ejecuciones.
                </div>
            </div>
        </div>
    );
}

// ─── Modal ────────────────────────────────────────────────────────────────────

interface ModalProps {
    automation: Automation | null;
    agents: Agent[];
    onClose: () => void;
    onSaved: (automation: Automation) => void;
}

function AutomationModal({ automation, agents, onClose, onSaved }: ModalProps) {
    const isEdit = automation !== null;

    const [name, setName] = useState(automation?.name ?? '');
    const [priority, setPriority] = useState(automation?.priority ?? 100);
    const [isActive, setIsActive] = useState(automation?.is_active ?? true);
    const [triggerType, setTriggerType] = useState<TriggerType>(
        automation?.trigger_type ?? 'new_conversation',
    );
    const [actionType, setActionType] = useState<ActionType>(
        automation?.action_type ?? 'send_message',
    );

    // Trigger config
    const [keywords, setKeywords] = useState<string>(
        (automation?.trigger_config?.keywords ?? []).join(', '),
    );
    const [matchType, setMatchType] = useState<'any' | 'all'>(
        automation?.trigger_config?.match_type ?? 'any',
    );
    const [caseInsensitive, setCaseInsensitive] = useState(
        automation?.trigger_config?.case_insensitive ?? true,
    );
    const [minutes, setMinutes] = useState(automation?.trigger_config?.minutes ?? 30);

    // Action config
    const [message, setMessage] = useState(automation?.action_config?.message ?? '');
    const [agentId, setAgentId] = useState(automation?.action_config?.agent_id ?? '');
    const [tags, setTags] = useState<string>((automation?.action_config?.tags ?? []).join(', '));
    const [stage, setStage] = useState<DealStage>(automation?.action_config?.stage ?? 'lead');

    const [saving, setSaving] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});

    function buildTriggerConfig(): TriggerConfig {
        switch (triggerType) {
            case 'keyword':
                return {
                    keywords: keywords
                        .split(',')
                        .map((k) => k.trim())
                        .filter(Boolean),
                    match_type: matchType,
                    case_insensitive: caseInsensitive,
                };
            case 'no_response_timeout':
                return { minutes };
            default:
                return {};
        }
    }

    function buildActionConfig(): ActionConfig {
        switch (actionType) {
            case 'send_message':
                return { message };
            case 'assign_agent':
                return { agent_id: agentId };
            case 'add_tag':
                return {
                    tags: tags
                        .split(',')
                        .map((t) => t.trim())
                        .filter(Boolean),
                };
            case 'move_stage':
                return { stage };
            default:
                return {};
        }
    }

    async function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        setSaving(true);
        setErrors({});

        const payload = {
            name: name.trim(),
            trigger_type: triggerType,
            trigger_config: buildTriggerConfig(),
            action_type: actionType,
            action_config: buildActionConfig(),
            is_active: isActive,
            priority,
        };

        try {
            const res = isEdit
                ? await axios.put(`/api/v1/automations/${automation!.id}`, payload)
                : await axios.post('/api/v1/automations', payload);
            onSaved(res.data.data as Automation);
        } catch (err: unknown) {
            if (axios.isAxiosError(err) && err.response?.data?.errors) {
                const errs: Record<string, string> = {};
                for (const [k, v] of Object.entries(
                    err.response.data.errors as Record<string, string[] | string>,
                )) {
                    errs[k] = Array.isArray(v) ? v[0] : v;
                }
                setErrors(errs);
            }
        } finally {
            setSaving(false);
        }
    }

    const inputCls =
        'w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-ari-500 focus:outline-none focus:ring-1 focus:ring-ari-500';

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div className="flex max-h-[90vh] w-full max-w-lg flex-col rounded-xl bg-white shadow-2xl">
                {/* Header */}
                <div className="flex items-center justify-between border-b border-gray-200 px-6 py-4">
                    <h2 className="text-lg font-semibold text-gray-900">
                        {isEdit ? 'Editar automatización' : 'Nueva automatización'}
                    </h2>
                    <button
                        onClick={onClose}
                        className="flex h-11 w-11 items-center justify-center rounded-lg text-gray-400 hover:bg-gray-100 hover:text-gray-600"
                    >
                        <X className="h-5 w-5" />
                    </button>
                </div>

                {/* Body */}
                <form onSubmit={handleSubmit} className="flex-1 space-y-4 overflow-y-auto px-6 py-5">
                    {/* Nombre */}
                    <div>
                        <label className="mb-1 block text-sm font-medium text-gray-700">Nombre</label>
                        <input
                            value={name}
                            onChange={(e) => setName(e.target.value)}
                            placeholder="Ej: Saludo fuera de horario"
                            className={inputCls}
                            required
                        />
                        {errors.name && <p className="mt-1 text-xs text-red-600">{errors.name}</p>}
                    </div>

                    {/* Prioridad */}
                    <div>
                        <label className="mb-1 block text-sm font-medium text-gray-700">
                            Prioridad{' '}
                            <span className="font-normal text-gray-400">(menor = primero)</span>
                        </label>
                        <input
                            type="number"
                            min={1}
                            max={999}
                            value={priority}
                            onChange={(e) => setPriority(Number(e.target.value))}
                            className={inputCls}
                        />
                    </div>

                    {/* ── Disparador ── */}
                    <div>
                        <label className="mb-1 block text-sm font-medium text-gray-700">
                            Disparador
                        </label>
                        <select
                            value={triggerType}
                            onChange={(e) => setTriggerType(e.target.value as TriggerType)}
                            className={inputCls}
                        >
                            {(Object.entries(TRIGGER_LABELS) as [TriggerType, string][]).map(
                                ([val, label]) => (
                                    <option key={val} value={val}>
                                        {label}
                                    </option>
                                ),
                            )}
                        </select>

                        {/* No config needed */}
                        {(triggerType === 'new_conversation' ||
                            triggerType === 'outside_hours') && (
                            <p className="mt-1.5 text-xs text-gray-400">
                                {TRIGGER_DESCRIPTIONS[triggerType]}
                            </p>
                        )}

                        {/* Config: keyword */}
                        {triggerType === 'keyword' && (
                            <div className="mt-3 space-y-3 rounded-lg border border-gray-200 bg-gray-50 p-3">
                                <div>
                                    <label className="mb-1 block text-xs font-medium text-gray-600">
                                        Palabras clave{' '}
                                        <span className="font-normal text-gray-400">
                                            (separadas por coma)
                                        </span>
                                    </label>
                                    <input
                                        value={keywords}
                                        onChange={(e) => setKeywords(e.target.value)}
                                        placeholder="hola, ayuda, precio"
                                        className={inputCls}
                                    />
                                    {errors['trigger_config.keywords'] && (
                                        <p className="mt-1 text-xs text-red-600">
                                            {errors['trigger_config.keywords']}
                                        </p>
                                    )}
                                </div>
                                <div className="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
                                    <div className="w-full sm:w-auto">
                                        <label className="mb-1 block text-xs font-medium text-gray-600">
                                            Condición
                                        </label>
                                        <select
                                            value={matchType}
                                            onChange={(e) =>
                                                setMatchType(e.target.value as 'any' | 'all')
                                            }
                                            className="min-w-0 w-full rounded-lg border border-gray-300 px-2 py-1.5 text-xs focus:border-ari-500 focus:outline-none sm:w-auto"
                                        >
                                            <option value="any">Cualquiera coincide</option>
                                            <option value="all">Todas coinciden</option>
                                        </select>
                                    </div>
                                    <label className="flex cursor-pointer items-center gap-1.5 text-xs text-gray-600">
                                        <input
                                            type="checkbox"
                                            checked={caseInsensitive}
                                            onChange={(e) => setCaseInsensitive(e.target.checked)}
                                            className="accent-ari-600"
                                        />
                                        Ignorar mayúsculas
                                    </label>
                                </div>
                            </div>
                        )}

                        {/* Config: no_response_timeout */}
                        {triggerType === 'no_response_timeout' && (
                            <div className="mt-3 rounded-lg border border-gray-200 bg-gray-50 p-3">
                                <label className="mb-1 block text-xs font-medium text-gray-600">
                                    Minutos sin respuesta del agente
                                </label>
                                <input
                                    type="number"
                                    min={1}
                                    max={1440}
                                    value={minutes}
                                    onChange={(e) => setMinutes(Number(e.target.value))}
                                    className="min-w-0 w-full rounded-lg border border-gray-300 px-3 py-1.5 text-sm focus:border-ari-500 focus:outline-none focus:ring-1 focus:ring-ari-500 sm:w-32"
                                />
                                <p className="mt-1 text-xs text-gray-400">
                                    El job corre cada minuto; la precisión es ±1 min.
                                </p>
                            </div>
                        )}
                    </div>

                    {/* ── Acción ── */}
                    <div>
                        <label className="mb-1 block text-sm font-medium text-gray-700">
                            Acción a ejecutar
                        </label>
                        <select
                            value={actionType}
                            onChange={(e) => setActionType(e.target.value as ActionType)}
                            className={inputCls}
                        >
                            {(Object.entries(ACTION_LABELS) as [ActionType, string][]).map(
                                ([val, label]) => (
                                    <option key={val} value={val}>
                                        {label}
                                    </option>
                                ),
                            )}
                        </select>

                        {/* Config: send_message */}
                        {actionType === 'send_message' && (
                            <div className="mt-3 rounded-lg border border-gray-200 bg-gray-50 p-3">
                                <label className="mb-1 block text-xs font-medium text-gray-600">
                                    Mensaje
                                </label>
                                <textarea
                                    value={message}
                                    onChange={(e) => setMessage(e.target.value)}
                                    rows={4}
                                    placeholder="Hola {{name}}, gracias por contactarnos. En este momento..."
                                    className="w-full resize-y rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-ari-500 focus:outline-none focus:ring-1 focus:ring-ari-500"
                                    required
                                />
                                <p className="mt-1 text-xs text-gray-400">
                                    Variables: <code>{'{{name}}'}</code>,{' '}
                                    <code>{'{{phone}}'}</code>, <code>{'{{company}}'}</code>
                                </p>
                                {errors['action_config.message'] && (
                                    <p className="mt-1 text-xs text-red-600">
                                        {errors['action_config.message']}
                                    </p>
                                )}
                            </div>
                        )}

                        {/* Config: assign_agent */}
                        {actionType === 'assign_agent' && (
                            <div className="mt-3 rounded-lg border border-gray-200 bg-gray-50 p-3">
                                <label className="mb-1 block text-xs font-medium text-gray-600">
                                    Agente
                                </label>
                                <select
                                    value={agentId}
                                    onChange={(e) => setAgentId(e.target.value)}
                                    className={inputCls}
                                    required
                                >
                                    <option value="">Selecciona un agente…</option>
                                    {agents.map((a) => (
                                        <option key={a.id} value={a.id}>
                                            {a.name} — {a.email}
                                        </option>
                                    ))}
                                </select>
                                {errors['action_config.agent_id'] && (
                                    <p className="mt-1 text-xs text-red-600">
                                        {errors['action_config.agent_id']}
                                    </p>
                                )}
                            </div>
                        )}

                        {/* Config: add_tag */}
                        {actionType === 'add_tag' && (
                            <div className="mt-3 rounded-lg border border-gray-200 bg-gray-50 p-3">
                                <label className="mb-1 block text-xs font-medium text-gray-600">
                                    Etiquetas{' '}
                                    <span className="font-normal text-gray-400">
                                        (separadas por coma)
                                    </span>
                                </label>
                                <input
                                    value={tags}
                                    onChange={(e) => setTags(e.target.value)}
                                    placeholder="premium, soporte, interesado"
                                    className={inputCls}
                                    required
                                />
                                {errors['action_config.tags'] && (
                                    <p className="mt-1 text-xs text-red-600">
                                        {errors['action_config.tags']}
                                    </p>
                                )}
                            </div>
                        )}

                        {/* Config: move_stage */}
                        {actionType === 'move_stage' && (
                            <div className="mt-3 rounded-lg border border-gray-200 bg-gray-50 p-3">
                                <label className="mb-1 block text-xs font-medium text-gray-600">
                                    Etapa del pipeline
                                </label>
                                <select
                                    value={stage}
                                    onChange={(e) => setStage(e.target.value as DealStage)}
                                    className={inputCls}
                                >
                                    {DEAL_STAGES.map((s) => (
                                        <option key={s.value} value={s.value}>
                                            {s.label}
                                        </option>
                                    ))}
                                </select>
                                <p className="mt-1 text-xs text-gray-400">
                                    Solo aplica si la conversación tiene un deal vinculado.
                                </p>
                            </div>
                        )}
                    </div>

                    {/* Activa */}
                    <label className="flex cursor-pointer items-center gap-3">
                        <div
                            role="switch"
                            aria-checked={isActive}
                            onClick={() => setIsActive(!isActive)}
                            className={`flex h-6 w-10 items-center rounded-full px-0.5 transition-colors ${isActive ? 'bg-ari-600' : 'bg-gray-300'}`}
                        >
                            <div
                                className={`h-5 w-5 rounded-full bg-white shadow transition-transform ${isActive ? 'translate-x-4' : 'translate-x-0'}`}
                            />
                        </div>
                        <span className="text-sm font-medium text-gray-700">
                            Automatización activa
                        </span>
                    </label>
                </form>

                {/* Footer */}
                <div className="flex flex-col-reverse gap-2 border-t border-gray-200 px-6 py-4 sm:flex-row sm:justify-end">
                    <button
                        type="button"
                        onClick={onClose}
                        className="min-h-11 w-full rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 sm:w-auto"
                    >
                        Cancelar
                    </button>
                    <button
                        onClick={handleSubmit as unknown as React.MouseEventHandler}
                        disabled={saving}
                        className="inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-lg bg-ari-600 px-4 py-2 text-sm font-medium text-white hover:bg-ari-700 disabled:opacity-60 sm:w-auto"
                    >
                        {saving && <Loader2 className="h-4 w-4 animate-spin" />}
                        {isEdit ? 'Guardar cambios' : 'Crear automatización'}
                    </button>
                </div>
            </div>
        </div>
    );
}

// ─── Tarjeta de automatización ────────────────────────────────────────────────

interface CardProps {
    automation: Automation;
    agents: Agent[];
    onEdit: () => void;
    onDelete: () => void;
    onToggle: () => void;
    onMoveUp: () => void;
    onMoveDown: () => void;
    onLogs: () => void;
    isFirst: boolean;
    isLast: boolean;
}

function AutomationCard({
    automation,
    agents,
    onEdit,
    onDelete,
    onToggle,
    onMoveUp,
    onMoveDown,
    onLogs,
    isFirst,
    isLast,
}: CardProps) {
    function summarizeTrigger(): string {
        const cfg = automation.trigger_config;
        switch (automation.trigger_type) {
            case 'keyword':
                return cfg.keywords?.length
                    ? cfg.keywords.join(', ')
                    : 'Sin palabras clave';
            case 'no_response_timeout':
                return `${cfg.minutes ?? '?'} min sin respuesta`;
            case 'new_conversation':
                return 'Al crear conversación';
            case 'outside_hours':
                return 'Fuera de horario laboral';
        }
    }

    function summarizeAction(): string {
        const cfg = automation.action_config;
        switch (automation.action_type) {
            case 'send_message':
                return cfg.message
                    ? `"${cfg.message.slice(0, 55)}${cfg.message.length > 55 ? '…' : ''}"`
                    : '—';
            case 'assign_agent': {
                const agent = agents.find((a) => a.id === cfg.agent_id);
                return agent ? agent.name : (cfg.agent_id ?? '—');
            }
            case 'add_tag':
                return cfg.tags?.join(', ') || '—';
            case 'move_stage':
                return DEAL_STAGES.find((s) => s.value === cfg.stage)?.label ?? (cfg.stage ?? '—');
        }
    }

    return (
        <div
            className={`flex flex-col gap-3 rounded-xl border bg-white p-4 shadow-sm transition-opacity sm:flex-row ${automation.is_active ? '' : 'opacity-55'}`}
        >
            {/* Priority controls */}
            <div className="flex flex-row items-center gap-2 sm:flex-col sm:gap-0.5 sm:pt-1">
                <button
                    onClick={onMoveUp}
                    disabled={isFirst}
                    title="Subir prioridad"
                    className="flex h-11 w-11 items-center justify-center rounded-lg text-gray-300 hover:bg-gray-100 hover:text-gray-500 disabled:opacity-20"
                >
                    <ChevronUp className="h-4 w-4" />
                </button>
                <GripVertical className="h-4 w-4 text-gray-200" />
                <button
                    onClick={onMoveDown}
                    disabled={isLast}
                    title="Bajar prioridad"
                    className="flex h-11 w-11 items-center justify-center rounded-lg text-gray-300 hover:bg-gray-100 hover:text-gray-500 disabled:opacity-20"
                >
                    <ChevronDown className="h-4 w-4" />
                </button>
            </div>

            {/* Content */}
            <div className="min-w-0 flex-1">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div className="min-w-0">
                        {/* Name + priority number */}
                        <div className="flex flex-wrap items-baseline gap-1.5">
                            <span className="font-semibold text-gray-900">{automation.name}</span>
                            <span className="text-xs text-gray-400">#{automation.priority}</span>
                        </div>

                        {/* Trigger → Action badges */}
                        <div className="mt-1.5 flex flex-wrap items-center gap-1.5">
                            <span
                                className={`inline-flex items-center rounded-full border px-2 py-0.5 text-xs font-medium ${TRIGGER_COLORS[automation.trigger_type]}`}
                            >
                                {TRIGGER_LABELS[automation.trigger_type]}
                            </span>
                            <span className="text-xs text-gray-400">→</span>
                            <span
                                className={`inline-flex items-center rounded-full border px-2 py-0.5 text-xs font-medium ${ACTION_COLORS[automation.action_type]}`}
                            >
                                {ACTION_LABELS[automation.action_type]}
                            </span>
                        </div>

                        {/* Summary line */}
                        <p className="mt-1 break-words text-xs text-gray-500">
                            {summarizeTrigger()} → {summarizeAction()}
                        </p>
                    </div>

                    {/* Actions toolbar */}
                    <div className="flex shrink-0 flex-wrap items-center gap-1 sm:justify-end">
                        {/* Execution count + logs */}
                        <button
                            onClick={onLogs}
                            className="mr-1 flex min-h-11 items-center gap-1 rounded-lg px-2 text-xs text-gray-400 hover:bg-gray-100 hover:text-ari-600"
                            title="Ver historial de ejecuciones"
                        >
                            <Play className="h-3 w-3" />
                            {automation.execution_count}
                        </button>

                        {/* Toggle */}
                        <button
                            onClick={onToggle}
                            title={automation.is_active ? 'Desactivar' : 'Activar'}
                            className={
                                automation.is_active
                                    ? 'flex h-11 w-11 items-center justify-center rounded-lg text-ari-500 hover:bg-gray-100 hover:text-ari-700'
                                    : 'flex h-11 w-11 items-center justify-center rounded-lg text-gray-400 hover:bg-gray-100 hover:text-gray-600'
                            }
                        >
                            {automation.is_active ? (
                                <ToggleRight className="h-5 w-5" />
                            ) : (
                                <ToggleLeft className="h-5 w-5" />
                            )}
                        </button>

                        {/* Edit */}
                        <button
                            onClick={onEdit}
                            title="Editar"
                            className="flex h-11 w-11 items-center justify-center rounded-md text-gray-400 hover:bg-gray-100 hover:text-gray-600"
                        >
                            <Pencil className="h-4 w-4" />
                        </button>

                        {/* Delete */}
                        <button
                            onClick={onDelete}
                            title="Eliminar"
                            className="flex h-11 w-11 items-center justify-center rounded-md text-gray-400 hover:bg-red-50 hover:text-red-500"
                        >
                            <Trash2 className="h-4 w-4" />
                        </button>
                    </div>
                </div>
            </div>
        </div>
    );
}

// ─── Página principal ─────────────────────────────────────────────────────────

export default function AutomationsPage() {
    const [automations, setAutomations] = useState<Automation[]>([]);
    const [agents, setAgents] = useState<Agent[]>([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [modalOpen, setModalOpen] = useState(false);
    const [editing, setEditing] = useState<Automation | null>(null);
    const [logsTarget, setLogsTarget] = useState<Automation | null>(null);

    useEffect(() => {
        Promise.all([
            axios.get<{ data: Automation[] }>('/api/v1/automations'),
            axios.get('/api/v1/team/members'),
        ])
            .then(([automRes, agentsRes]) => {
                setAutomations(automRes.data.data);
                setAgents((agentsRes.data.data as Agent[]) ?? (agentsRes.data as Agent[]));
            })
            .catch(() => setError('No se pudieron cargar las automatizaciones.'))
            .finally(() => setLoading(false));
    }, []);

    function openCreate() {
        setEditing(null);
        setModalOpen(true);
    }

    function openEdit(automation: Automation) {
        setEditing(automation);
        setModalOpen(true);
    }

    function handleSaved(saved: Automation) {
        setAutomations((prev) => {
            const idx = prev.findIndex((a) => a.id === saved.id);
            const next =
                idx >= 0 ? prev.map((a) => (a.id === saved.id ? saved : a)) : [...prev, saved];
            return [...next].sort((a, b) => a.priority - b.priority);
        });
        setModalOpen(false);
    }

    async function handleDelete(automation: Automation) {
        if (!confirm(`¿Eliminar la automatización "${automation.name}"?`)) return;
        try {
            await axios.delete(`/api/v1/automations/${automation.id}`);
            setAutomations((prev) => prev.filter((a) => a.id !== automation.id));
        } catch {
            setError('No se pudo eliminar la automatización.');
        }
    }

    async function handleToggle(automation: Automation) {
        try {
            const res = await axios.patch(`/api/v1/automations/${automation.id}/toggle`);
            setAutomations((prev) =>
                prev.map((a) => (a.id === automation.id ? (res.data.data as Automation) : a)),
            );
        } catch {
            setError('No se pudo cambiar el estado de la automatización.');
        }
    }

    async function handleMove(index: number, direction: 'up' | 'down') {
        const swapIndex = direction === 'up' ? index - 1 : index + 1;
        const reordered = [...automations];

        // Swap priorities
        const tmpPriority = reordered[index].priority;
        reordered[index] = { ...reordered[index], priority: reordered[swapIndex].priority };
        reordered[swapIndex] = { ...reordered[swapIndex], priority: tmpPriority };
        [reordered[index], reordered[swapIndex]] = [reordered[swapIndex], reordered[index]];
        setAutomations(reordered);

        // Persist both
        await Promise.all([
            axios.put(`/api/v1/automations/${reordered[index].id}`, {
                name: reordered[index].name,
                trigger_type: reordered[index].trigger_type,
                trigger_config: reordered[index].trigger_config,
                action_type: reordered[index].action_type,
                action_config: reordered[index].action_config,
                is_active: reordered[index].is_active,
                priority: reordered[index].priority,
            }),
            axios.put(`/api/v1/automations/${reordered[swapIndex].id}`, {
                name: reordered[swapIndex].name,
                trigger_type: reordered[swapIndex].trigger_type,
                trigger_config: reordered[swapIndex].trigger_config,
                action_type: reordered[swapIndex].action_type,
                action_config: reordered[swapIndex].action_config,
                is_active: reordered[swapIndex].is_active,
                priority: reordered[swapIndex].priority,
            }),
        ]);
    }

    return (
        <AppLayout title="Automatizaciones">
            <Head title="Automatizaciones" />

            <div className="mx-auto max-w-2xl space-y-5 px-4 py-8">
                {/* Header */}
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold text-gray-900">Automatizaciones</h1>
                        <p className="mt-1 text-sm text-gray-500">
                            Responde, asigna o etiqueta conversaciones según disparadores.
                            Las reglas se evalúan en orden de prioridad.
                        </p>
                    </div>
                    <button
                        onClick={openCreate}
                        className="inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-lg bg-ari-600 px-4 py-2 text-sm font-medium text-white hover:bg-ari-700 sm:w-auto"
                    >
                        <Plus className="h-4 w-4" />
                        Nueva automatización
                    </button>
                </div>

                {/* Error banner */}
                {error && (
                    <div className="flex items-center justify-between rounded-lg border border-red-100 bg-red-50 px-4 py-3 text-sm text-red-700">
                        <span>{error}</span>
                        <button onClick={() => setError(null)} className="flex h-11 w-11 items-center justify-center rounded-lg">
                            <X className="h-4 w-4" />
                        </button>
                    </div>
                )}

                {/* Content */}
                {loading ? (
                    <div className="flex items-center justify-center gap-2 py-16 text-sm text-gray-400">
                        <Loader2 className="h-4 w-4 animate-spin" />
                        Cargando automatizaciones…
                    </div>
                ) : automations.length === 0 ? (
                    <div className="flex flex-col items-center justify-center gap-3 rounded-xl border-2 border-dashed border-gray-200 py-16 text-center">
                        <Bot className="h-10 w-10 text-gray-300" />
                        <div>
                            <p className="text-sm font-medium text-gray-500">
                                Sin automatizaciones aún
                            </p>
                            <p className="mt-1 text-xs text-gray-400">
                                Crea tu primera automatización para responder o asignar
                                conversaciones automáticamente.
                            </p>
                        </div>
                        <button
                            onClick={openCreate}
                            className="mt-1 inline-flex min-h-11 items-center justify-center rounded-lg bg-ari-600 px-4 py-2 text-sm font-medium text-white hover:bg-ari-700"
                        >
                            Crear automatización
                        </button>
                    </div>
                ) : (
                    <div className="space-y-3">
                        {automations.map((automation, index) => (
                            <AutomationCard
                                key={automation.id}
                                automation={automation}
                                agents={agents}
                                onEdit={() => openEdit(automation)}
                                onDelete={() => void handleDelete(automation)}
                                onToggle={() => void handleToggle(automation)}
                                onMoveUp={() => void handleMove(index, 'up')}
                                onMoveDown={() => void handleMove(index, 'down')}
                                onLogs={() => setLogsTarget(automation)}
                                isFirst={index === 0}
                                isLast={index === automations.length - 1}
                            />
                        ))}
                    </div>
                )}
            </div>

            {modalOpen && (
                <AutomationModal
                    automation={editing}
                    agents={agents}
                    onClose={() => setModalOpen(false)}
                    onSaved={handleSaved}
                />
            )}

            {logsTarget && (
                <LogsDrawer
                    automation={logsTarget}
                    onClose={() => setLogsTarget(null)}
                />
            )}
        </AppLayout>
    );
}

import { useState, useEffect } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { Head } from '@inertiajs/react';
import axios from 'axios';
import {
    Plus,
    Pencil,
    Trash2,
    GripVertical,
    ToggleLeft,
    ToggleRight,
    ChevronDown,
    ChevronUp,
    X,
} from 'lucide-react';

// ─── Types ───────────────────────────────────────────────────────────────────

interface TagMapping {
    tag: string;
    agent_ids: string[];
}

interface RuleConfig {
    agent_ids?: string[];
    max_conversations?: number;
    tag_mappings?: TagMapping[];
}

interface AssignmentRule {
    id: string;
    name: string;
    type: 'round_robin' | 'least_busy' | 'tag_based' | 'manual';
    priority: number;
    is_active: boolean;
    config: RuleConfig;
    created_at: string;
    updated_at: string;
}

interface Agent {
    id: string;
    name: string;
    email: string;
}

const TYPE_LABELS: Record<string, string> = {
    round_robin: 'Turno rotativo',
    least_busy: 'Menos ocupado',
    tag_based: 'Por etiqueta',
    manual: 'Manual',
};

const TYPE_DESCRIPTIONS: Record<string, string> = {
    round_robin: 'Asigna en orden secuencial entre los agentes del grupo.',
    least_busy: 'Asigna al agente con menos conversaciones activas.',
    tag_based: 'Asigna según las etiquetas del contacto.',
    manual: 'No asigna automáticamente.',
};

// ─── Modal de regla ──────────────────────────────────────────────────────────

interface RuleModalProps {
    rule: AssignmentRule | null;
    agents: Agent[];
    onClose: () => void;
    onSaved: (rule: AssignmentRule) => void;
}

function RuleModal({ rule, agents, onClose, onSaved }: RuleModalProps) {
    const isEdit = rule !== null;

    const [name, setName] = useState(rule?.name ?? '');
    const [type, setType] = useState<string>(rule?.type ?? 'round_robin');
    const [priority, setPriority] = useState<number>(rule?.priority ?? 10);
    const [isActive, setIsActive] = useState(rule?.is_active ?? true);
    const [agentIds, setAgentIds] = useState<string[]>(rule?.config?.agent_ids ?? []);
    const [maxConversations, setMaxConversations] = useState<number>(
        rule?.config?.max_conversations ?? 20,
    );
    const [tagMappings, setTagMappings] = useState<TagMapping[]>(
        rule?.config?.tag_mappings ?? [],
    );
    const [saving, setSaving] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});

    function buildConfig(): RuleConfig {
        if (type === 'round_robin') return { agent_ids: agentIds };
        if (type === 'least_busy') return { agent_ids: agentIds, max_conversations: maxConversations };
        if (type === 'tag_based') return { tag_mappings: tagMappings };
        return {};
    }

    async function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        setSaving(true);
        setErrors({});
        try {
            const payload = { name, type, priority, is_active: isActive, config: buildConfig() };
            const res = isEdit
                ? await axios.put(`/api/v1/assignment-rules/${rule!.id}`, payload)
                : await axios.post('/api/v1/assignment-rules', payload);
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

    // Tag-based helpers
    function addTagMapping() {
        setTagMappings([...tagMappings, { tag: '', agent_ids: [] }]);
    }
    function removeTagMapping(i: number) {
        setTagMappings(tagMappings.filter((_, idx) => idx !== i));
    }
    function updateTagMapping(i: number, field: keyof TagMapping, value: string | string[]) {
        setTagMappings(tagMappings.map((m, idx) => (idx === i ? { ...m, [field]: value } : m)));
    }

    // Agent checkbox helper
    function toggleAgent(agentId: string, list: string[], setter: (v: string[]) => void) {
        setter(list.includes(agentId) ? list.filter((a) => a !== agentId) : [...list, agentId]);
    }

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div className="bg-white rounded-xl shadow-2xl w-full max-w-lg max-h-[90vh] flex flex-col">
                {/* Header */}
                <div className="flex items-center justify-between px-6 py-4 border-b">
                    <h2 className="text-lg font-semibold text-gray-900">
                        {isEdit ? 'Editar regla' : 'Nueva regla'}
                    </h2>
                    <button onClick={onClose} className="text-gray-400 hover:text-gray-600">
                        <X size={20} />
                    </button>
                </div>

                {/* Body */}
                <form onSubmit={handleSubmit} className="overflow-y-auto flex-1 px-6 py-4 space-y-4">
                    {/* Nombre */}
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Nombre</label>
                        <input
                            value={name}
                            onChange={(e) => setName(e.target.value)}
                            className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"
                            placeholder="Ej: Rotativo — Soporte"
                            required
                        />
                        {errors.name && <p className="text-xs text-red-500 mt-1">{errors.name}</p>}
                    </div>

                    {/* Tipo */}
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Tipo</label>
                        <select
                            value={type}
                            onChange={(e) => setType(e.target.value)}
                            className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"
                        >
                            {Object.entries(TYPE_LABELS).map(([val, label]) => (
                                <option key={val} value={val}>{label}</option>
                            ))}
                        </select>
                        <p className="text-xs text-gray-500 mt-1">{TYPE_DESCRIPTIONS[type]}</p>
                    </div>

                    {/* Prioridad */}
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">
                            Prioridad <span className="text-gray-400">(menor = primero)</span>
                        </label>
                        <input
                            type="number"
                            min={1}
                            max={999}
                            value={priority}
                            onChange={(e) => setPriority(Number(e.target.value))}
                            className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"
                        />
                        {errors.priority && <p className="text-xs text-red-500 mt-1">{errors.priority}</p>}
                    </div>

                    {/* Config: round_robin / least_busy → agentes */}
                    {(type === 'round_robin' || type === 'least_busy') && (
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-2">
                                Agentes del grupo{' '}
                                <span className="text-gray-400">(vacío = todos)</span>
                            </label>
                            <div className="space-y-1 max-h-40 overflow-y-auto border border-gray-200 rounded-lg p-2">
                                {agents.map((agent) => (
                                    <label key={agent.id} className="flex items-center gap-2 text-sm cursor-pointer">
                                        <input
                                            type="checkbox"
                                            checked={agentIds.includes(agent.id)}
                                            onChange={() => toggleAgent(agent.id, agentIds, setAgentIds)}
                                            className="accent-emerald-500"
                                        />
                                        <span>{agent.name}</span>
                                        <span className="text-gray-400 text-xs">{agent.email}</span>
                                    </label>
                                ))}
                            </div>
                            {type === 'least_busy' && (
                                <div className="mt-2">
                                    <label className="block text-xs text-gray-600 mb-1">
                                        Máx. conversaciones simultáneas por agente
                                    </label>
                                    <input
                                        type="number"
                                        min={1}
                                        value={maxConversations}
                                        onChange={(e) => setMaxConversations(Number(e.target.value))}
                                        className="w-32 rounded-lg border border-gray-300 px-3 py-1 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"
                                    />
                                </div>
                            )}
                        </div>
                    )}

                    {/* Config: tag_based → mappings */}
                    {type === 'tag_based' && (
                        <div>
                            <div className="flex items-center justify-between mb-2">
                                <label className="block text-sm font-medium text-gray-700">
                                    Mapeos etiqueta → agentes
                                </label>
                                <button
                                    type="button"
                                    onClick={addTagMapping}
                                    className="text-xs text-emerald-600 hover:text-emerald-700 flex items-center gap-1"
                                >
                                    <Plus size={12} /> Agregar
                                </button>
                            </div>
                            <div className="space-y-3">
                                {tagMappings.map((mapping, i) => (
                                    <div key={i} className="border border-gray-200 rounded-lg p-3 space-y-2">
                                        <div className="flex items-center gap-2">
                                            <input
                                                value={mapping.tag}
                                                onChange={(e) => updateTagMapping(i, 'tag', e.target.value)}
                                                placeholder="Etiqueta (ej: premium)"
                                                className="flex-1 rounded border border-gray-300 px-2 py-1 text-sm focus:outline-none focus:ring-1 focus:ring-emerald-500"
                                            />
                                            <button
                                                type="button"
                                                onClick={() => removeTagMapping(i)}
                                                className="text-red-400 hover:text-red-600"
                                            >
                                                <X size={14} />
                                            </button>
                                        </div>
                                        <div className="space-y-1 max-h-28 overflow-y-auto">
                                            {agents.map((agent) => (
                                                <label key={agent.id} className="flex items-center gap-2 text-xs cursor-pointer">
                                                    <input
                                                        type="checkbox"
                                                        checked={mapping.agent_ids.includes(agent.id)}
                                                        onChange={() =>
                                                            updateTagMapping(
                                                                i,
                                                                'agent_ids',
                                                                mapping.agent_ids.includes(agent.id)
                                                                    ? mapping.agent_ids.filter((a) => a !== agent.id)
                                                                    : [...mapping.agent_ids, agent.id],
                                                            )
                                                        }
                                                        className="accent-emerald-500"
                                                    />
                                                    {agent.name}
                                                </label>
                                            ))}
                                        </div>
                                    </div>
                                ))}
                                {tagMappings.length === 0 && (
                                    <p className="text-xs text-gray-400 text-center py-2">
                                        Sin mapeos. Agrega uno con el botón de arriba.
                                    </p>
                                )}
                            </div>
                        </div>
                    )}

                    {/* Activa */}
                    <label className="flex items-center gap-3 cursor-pointer">
                        <div
                            className={`w-10 h-6 rounded-full transition-colors ${isActive ? 'bg-emerald-500' : 'bg-gray-300'} flex items-center px-0.5`}
                            onClick={() => setIsActive(!isActive)}
                        >
                            <div
                                className={`w-5 h-5 bg-white rounded-full shadow transition-transform ${isActive ? 'translate-x-4' : 'translate-x-0'}`}
                            />
                        </div>
                        <span className="text-sm font-medium text-gray-700">Regla activa</span>
                    </label>
                </form>

                {/* Footer */}
                <div className="flex justify-end gap-2 px-6 py-4 border-t">
                    <button
                        type="button"
                        onClick={onClose}
                        className="px-4 py-2 text-sm rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50"
                    >
                        Cancelar
                    </button>
                    <button
                        onClick={handleSubmit as unknown as React.MouseEventHandler}
                        disabled={saving}
                        className="px-4 py-2 text-sm rounded-lg bg-emerald-600 text-white hover:bg-emerald-700 disabled:opacity-50"
                    >
                        {saving ? 'Guardando…' : isEdit ? 'Guardar cambios' : 'Crear regla'}
                    </button>
                </div>
            </div>
        </div>
    );
}

// ─── Tarjeta de regla ─────────────────────────────────────────────────────────

interface RuleCardProps {
    rule: AssignmentRule;
    agents: Agent[];
    onEdit: () => void;
    onDelete: () => void;
    onToggle: () => void;
    onMoveUp: () => void;
    onMoveDown: () => void;
    isFirst: boolean;
    isLast: boolean;
}

function RuleCard({ rule, agents, onEdit, onDelete, onToggle, onMoveUp, onMoveDown, isFirst, isLast }: RuleCardProps) {
    function agentName(id: string) {
        return agents.find((a) => a.id === id)?.name ?? id;
    }

    function configSummary() {
        if (rule.type === 'round_robin' || rule.type === 'least_busy') {
            const ids = rule.config?.agent_ids ?? [];
            if (ids.length === 0) return 'Todos los agentes';
            return ids.map(agentName).join(', ');
        }
        if (rule.type === 'tag_based') {
            const mappings = rule.config?.tag_mappings ?? [];
            if (mappings.length === 0) return 'Sin mapeos';
            return mappings.map((m) => `"${m.tag}" → ${m.agent_ids.map(agentName).join(', ')}`).join(' | ');
        }
        return '—';
    }

    return (
        <div className={`bg-white rounded-xl border shadow-sm p-4 flex gap-3 items-start transition-opacity ${rule.is_active ? '' : 'opacity-60'}`}>
            {/* Drag handle / priority controls */}
            <div className="flex flex-col items-center gap-0.5 pt-1">
                <button
                    onClick={onMoveUp}
                    disabled={isFirst}
                    className="text-gray-300 hover:text-gray-500 disabled:opacity-20"
                >
                    <ChevronUp size={16} />
                </button>
                <GripVertical size={16} className="text-gray-300" />
                <button
                    onClick={onMoveDown}
                    disabled={isLast}
                    className="text-gray-300 hover:text-gray-500 disabled:opacity-20"
                >
                    <ChevronDown size={16} />
                </button>
            </div>

            {/* Content */}
            <div className="flex-1 min-w-0">
                <div className="flex items-start justify-between gap-2">
                    <div>
                        <span className="font-medium text-gray-900">{rule.name}</span>
                        <span className="ml-2 text-xs px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200">
                            {TYPE_LABELS[rule.type]}
                        </span>
                        <span className="ml-1 text-xs text-gray-400">#{rule.priority}</span>
                    </div>
                    <div className="flex items-center gap-1 shrink-0">
                        <button
                            onClick={onToggle}
                            className={`text-sm ${rule.is_active ? 'text-emerald-500 hover:text-emerald-700' : 'text-gray-400 hover:text-gray-600'}`}
                            title={rule.is_active ? 'Desactivar' : 'Activar'}
                        >
                            {rule.is_active ? <ToggleRight size={20} /> : <ToggleLeft size={20} />}
                        </button>
                        <button
                            onClick={onEdit}
                            className="text-gray-400 hover:text-gray-600 p-1"
                        >
                            <Pencil size={15} />
                        </button>
                        <button
                            onClick={onDelete}
                            className="text-gray-400 hover:text-red-500 p-1"
                        >
                            <Trash2 size={15} />
                        </button>
                    </div>
                </div>
                <p className="text-xs text-gray-500 mt-1 truncate">{configSummary()}</p>
                {rule.type === 'least_busy' && rule.config?.max_conversations && (
                    <p className="text-xs text-gray-400 mt-0.5">
                        Máx. {rule.config.max_conversations} convs/agente
                    </p>
                )}
            </div>
        </div>
    );
}

// ─── Página principal ─────────────────────────────────────────────────────────

export default function AssignmentRules() {
    const [rules, setRules] = useState<AssignmentRule[]>([]);
    const [agents, setAgents] = useState<Agent[]>([]);
    const [loading, setLoading] = useState(true);
    const [modalOpen, setModalOpen] = useState(false);
    const [editingRule, setEditingRule] = useState<AssignmentRule | null>(null);

    useEffect(() => {
        Promise.all([
            axios.get('/api/v1/assignment-rules'),
            axios.get('/api/v1/team/members'),
        ]).then(([rulesRes, agentsRes]) => {
            setRules(rulesRes.data.data);
            setAgents(agentsRes.data.data ?? agentsRes.data);
        }).finally(() => setLoading(false));
    }, []);

    function openCreate() {
        setEditingRule(null);
        setModalOpen(true);
    }

    function openEdit(rule: AssignmentRule) {
        setEditingRule(rule);
        setModalOpen(true);
    }

    function handleSaved(saved: AssignmentRule) {
        setRules((prev) => {
            const idx = prev.findIndex((r) => r.id === saved.id);
            if (idx >= 0) {
                const next = [...prev];
                next[idx] = saved;
                return next.sort((a, b) => a.priority - b.priority);
            }
            return [...prev, saved].sort((a, b) => a.priority - b.priority);
        });
        setModalOpen(false);
    }

    async function handleDelete(rule: AssignmentRule) {
        if (!confirm(`¿Eliminar la regla "${rule.name}"?`)) return;
        await axios.delete(`/api/v1/assignment-rules/${rule.id}`);
        setRules((prev) => prev.filter((r) => r.id !== rule.id));
    }

    async function handleToggle(rule: AssignmentRule) {
        const res = await axios.patch(`/api/v1/assignment-rules/${rule.id}/toggle`);
        setRules((prev) => prev.map((r) => (r.id === rule.id ? res.data.data : r)));
    }

    async function handleMove(index: number, direction: 'up' | 'down') {
        const swapIndex = direction === 'up' ? index - 1 : index + 1;
        const reordered = [...rules];
        // Swap priorities
        const tmpPriority = reordered[index].priority;
        reordered[index] = { ...reordered[index], priority: reordered[swapIndex].priority };
        reordered[swapIndex] = { ...reordered[swapIndex], priority: tmpPriority };
        [reordered[index], reordered[swapIndex]] = [reordered[swapIndex], reordered[index]];
        setRules(reordered);

        // Persist both updated priorities
        await Promise.all([
            axios.put(`/api/v1/assignment-rules/${reordered[index].id}`, {
                name: reordered[index].name,
                type: reordered[index].type,
                priority: reordered[index].priority,
                is_active: reordered[index].is_active,
                config: reordered[index].config,
            }),
            axios.put(`/api/v1/assignment-rules/${reordered[swapIndex].id}`, {
                name: reordered[swapIndex].name,
                type: reordered[swapIndex].type,
                priority: reordered[swapIndex].priority,
                is_active: reordered[swapIndex].is_active,
                config: reordered[swapIndex].config,
            }),
        ]);
    }

    return (
        <AppLayout header={<h2 className="text-xl font-semibold text-gray-800">Reglas de asignación</h2>}>
            <Head title="Reglas de asignación" />

            <div className="max-w-2xl mx-auto py-8 px-4">
                {/* Header actions */}
                <div className="flex items-center justify-between mb-6">
                    <div>
                        <p className="text-sm text-gray-500">
                            Define cómo se asignan automáticamente las conversaciones entrantes a los agentes.
                            Las reglas se evalúan en orden de prioridad; gana la primera que aplica.
                        </p>
                    </div>
                    <button
                        onClick={openCreate}
                        className="flex items-center gap-2 px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm font-medium hover:bg-emerald-700"
                    >
                        <Plus size={16} />
                        Nueva regla
                    </button>
                </div>

                {/* List */}
                {loading ? (
                    <div className="text-center py-16 text-gray-400 text-sm">Cargando…</div>
                ) : rules.length === 0 ? (
                    <div className="text-center py-16 border-2 border-dashed border-gray-200 rounded-xl">
                        <GripVertical size={32} className="text-gray-300 mx-auto mb-3" />
                        <p className="text-gray-500 text-sm font-medium">Sin reglas aún</p>
                        <p className="text-gray-400 text-xs mt-1">
                            Crea tu primera regla para asignar conversaciones automáticamente.
                        </p>
                        <button
                            onClick={openCreate}
                            className="mt-4 px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm hover:bg-emerald-700"
                        >
                            Crear regla
                        </button>
                    </div>
                ) : (
                    <div className="space-y-3">
                        {rules.map((rule, index) => (
                            <RuleCard
                                key={rule.id}
                                rule={rule}
                                agents={agents}
                                onEdit={() => openEdit(rule)}
                                onDelete={() => handleDelete(rule)}
                                onToggle={() => handleToggle(rule)}
                                onMoveUp={() => handleMove(index, 'up')}
                                onMoveDown={() => handleMove(index, 'down')}
                                isFirst={index === 0}
                                isLast={index === rules.length - 1}
                            />
                        ))}
                    </div>
                )}
            </div>

            {modalOpen && (
                <RuleModal
                    rule={editingRule}
                    agents={agents}
                    onClose={() => setModalOpen(false)}
                    onSaved={handleSaved}
                />
            )}
        </AppLayout>
    );
}

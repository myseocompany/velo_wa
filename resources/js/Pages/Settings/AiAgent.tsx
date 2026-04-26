import ChatBubble, { ChatRole } from '@/Components/Chat/ChatBubble';
import AppLayout from '@/Layouts/AppLayout';
import { Head } from '@inertiajs/react';
import axios from 'axios';
import { AlertTriangle, Loader2, Plus, RotateCcw, Save, Send, Sparkles, Star, Trash2 } from 'lucide-react';
import { FormEvent, useEffect, useMemo, useRef, useState } from 'react';

interface AiAgentConfig {
    id: string;
    name: string;
    system_prompt: string | null;
    llm_model: string;
    is_enabled: boolean;
    is_default: boolean;
    context_messages: number;
    is_configured: boolean;
}

interface AgentPayload {
    name: string;
    system_prompt: string | null;
    llm_model: string;
    is_enabled: boolean;
    context_messages: number;
}

interface PlaygroundMessage {
    role: ChatRole;
    content: string;
    meta?: string;
}

interface PlaygroundResponse {
    data: {
        reply: string;
        provider: string;
        model: string;
    };
}


function extractApiError(err: unknown, fallback: string): string {
    if (!axios.isAxiosError(err)) return fallback;

    const message = err.response?.data?.message;
    if (typeof message === 'string' && message.trim() !== '') return message;

    const firstError = err.response?.data?.errors
        ? Object.values(err.response.data.errors)[0]
        : null;

    if (Array.isArray(firstError) && firstError.length > 0) {
        return String(firstError[0]);
    }

    if (typeof firstError === 'string') return firstError;

    return fallback;
}

export default function SettingsAiAgent() {
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [busy, setBusy] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [availableModels, setAvailableModels] = useState<string[]>([]);
    const [agents, setAgents] = useState<AiAgentConfig[]>([]);
    const [selectedId, setSelectedId] = useState<string | null>(null);
    const [playgroundMessages, setPlaygroundMessages] = useState<PlaygroundMessage[]>([]);
    const [playgroundInput, setPlaygroundInput] = useState('');
    const [playgroundSending, setPlaygroundSending] = useState(false);
    const [apiStatus, setApiStatus] = useState<'unknown' | 'ok' | 'error'>('unknown');
    const [apiError, setApiError] = useState<string | null>(null);
    const [form, setForm] = useState<AgentPayload>({
        name: 'Agente IA',
        system_prompt: '',
        llm_model: 'claude-haiku-4-5',
        is_enabled: false,
        context_messages: 10,
    });

    const selectedAgent = useMemo(
        () => agents.find((a) => a.id === selectedId) ?? null,
        [agents, selectedId],
    );

    async function loadConfig() {
        setLoading(true);
        try {
            const res = await axios.get<{ data: AiAgentConfig[]; available_models: string[] }>('/api/v1/ai-agents');
            const list = res.data.data ?? [];
            setAgents(list);
            setAvailableModels(res.data.available_models ?? []);

            if (list.length > 0) {
                const selected = list.find((a) => a.is_default) ?? list[0];
                setSelectedId(selected.id);
                setForm({
                    name: selected.name,
                    system_prompt: selected.system_prompt,
                    llm_model: selected.llm_model,
                    is_enabled: selected.is_enabled,
                    context_messages: selected.context_messages,
                });
            } else {
                setSelectedId(null);
            }
        } finally {
            setLoading(false);
        }
    }

    useEffect(() => {
        void loadConfig();
    }, []);

    function selectAgent(agent: AiAgentConfig) {
        setSelectedId(agent.id);
        setErrors({});
        setPlaygroundMessages([]);
        setPlaygroundInput('');
        setApiStatus('unknown');
        setApiError(null);
        setForm({
            name: agent.name,
            system_prompt: agent.system_prompt,
            llm_model: agent.llm_model,
            is_enabled: agent.is_enabled,
            context_messages: agent.context_messages,
        });
    }

    async function createAgent() {
        setBusy(true);
        setErrors({});
        try {
            const nextNumber = agents.length + 1;
            const payload: AgentPayload = {
                name: `Agente IA ${nextNumber}`,
                system_prompt: '',
                llm_model: availableModels[0] ?? 'claude-haiku-4-5',
                is_enabled: false,
                context_messages: 10,
            };

            const res = await axios.post<{ data: AiAgentConfig }>('/api/v1/ai-agents', payload);
            const created = res.data.data;
            const nextList = [created, ...agents];
            setAgents(nextList);
            selectAgent(created);
        } catch (err: unknown) {
            setErrors({ form: extractApiError(err, 'No se pudo crear el agente.') });
        } finally {
            setBusy(false);
        }
    }

    async function saveSelected(e: FormEvent) {
        e.preventDefault();
        if (!selectedId) return;

        setSaving(true);
        setErrors({});
        try {
            const res = await axios.put<{ data: AiAgentConfig }>(`/api/v1/ai-agents/${selectedId}`, form);
            const updated = res.data.data;
            setAgents((prev) => prev.map((a) => (a.id === updated.id ? updated : a)));
            selectAgent(updated);
        } catch (err: unknown) {
            if (axios.isAxiosError(err) && err.response?.data?.errors) {
                const nextErrors: Record<string, string> = {};
                for (const [field, value] of Object.entries(err.response.data.errors)) {
                    nextErrors[field] = Array.isArray(value) ? (value as string[])[0] : String(value);
                }
                setErrors(nextErrors);
            }
        } finally {
            setSaving(false);
        }
    }

    async function toggleSelected() {
        if (!selectedId || !selectedAgent) return;
        setBusy(true);
        setErrors({});
        try {
            const res = await axios.patch<{ data: AiAgentConfig }>(`/api/v1/ai-agents/${selectedId}/toggle`, {
                enabled: !selectedAgent.is_enabled,
            });
            const updated = res.data.data;
            setAgents((prev) => prev.map((a) => (a.id === updated.id ? updated : a)));
            selectAgent(updated);
        } catch (err: unknown) {
            setErrors({ form: extractApiError(err, 'No se pudo cambiar el estado del agente.') });
        } finally {
            setBusy(false);
        }
    }

    async function setDefaultSelected() {
        if (!selectedId) return;
        setBusy(true);
        setErrors({});
        try {
            const res = await axios.patch<{ data: AiAgentConfig }>(`/api/v1/ai-agents/${selectedId}/default`);
            const updated = res.data.data;
            setAgents((prev) => prev.map((a) => ({ ...a, is_default: a.id === updated.id })));
        } catch (err: unknown) {
            setErrors({ form: extractApiError(err, 'No se pudo marcar el agente como predeterminado.') });
        } finally {
            setBusy(false);
        }
    }

    async function deleteSelected() {
        if (!selectedId || !selectedAgent) return;
        if (!window.confirm(`Eliminar ${selectedAgent.name}?`)) return;

        setBusy(true);
        setErrors({});
        try {
            await axios.delete(`/api/v1/ai-agents/${selectedId}`);
            const next = agents.filter((a) => a.id !== selectedId);
            setAgents(next);

            if (next.length > 0) {
                const first = next.find((a) => a.is_default) ?? next[0];
                selectAgent(first);
            } else {
                setSelectedId(null);
                setForm({
                    name: 'Agente IA',
                    system_prompt: '',
                    llm_model: availableModels[0] ?? 'claude-haiku-4-5',
                    is_enabled: false,
                    context_messages: 10,
                });
            }
        } catch (err: unknown) {
            setErrors({ form: extractApiError(err, 'No se pudo eliminar el agente.') });
        } finally {
            setBusy(false);
        }
    }

    async function sendPlayground(e?: FormEvent) {
        e?.preventDefault();
        if (!selectedId || playgroundSending) return;

        const text = playgroundInput.trim();
        if (!text) return;

        const userMessage: PlaygroundMessage = { role: 'user', content: text };
        const history = playgroundMessages.map(({ role, content }) => ({ role, content }));

        setPlaygroundMessages((prev) => [...prev, userMessage]);
        setPlaygroundInput('');
        setPlaygroundSending(true);
        setApiError(null);

        try {
            const res = await axios.post<PlaygroundResponse>(`/api/v1/ai-agents/${selectedId}/playground`, {
                message: text,
                history,
            });

            setPlaygroundMessages((prev) => [
                ...prev,
                {
                    role: 'assistant',
                    content: res.data.data.reply,
                    meta: `${res.data.data.provider} / ${res.data.data.model}`,
                },
            ]);
            setApiStatus('ok');
        } catch (err: unknown) {
            setApiStatus('error');
            setApiError(extractApiError(err, 'No se pudo probar el agente.'));
        } finally {
            setPlaygroundSending(false);
        }
    }

    return (
        <AppLayout title="Agente IA">
            <Head title="Agente IA" />

            <div className="mx-auto max-w-6xl space-y-5 p-6">
                <div className="flex items-center justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold text-gray-900">Agentes IA</h1>
                        <p className="mt-1 text-sm text-gray-500">
                            Crea varios agentes por tenant y define uno como predeterminado para respuestas automáticas.
                        </p>
                    </div>

                    <button
                        onClick={createAgent}
                        disabled={loading || busy}
                        className="inline-flex min-h-11 items-center gap-2 rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50"
                    >
                        {busy ? <Loader2 className="h-4 w-4 animate-spin" /> : <Plus className="h-4 w-4" />}
                        Nuevo agente
                    </button>
                </div>

                {loading ? (
                    <div className="flex h-52 items-center justify-center rounded-xl border border-gray-200 bg-white">
                        <Loader2 className="h-6 w-6 animate-spin text-ari-600" />
                    </div>
                ) : (
                    <div className="grid gap-4 lg:grid-cols-3">
                        <div className="space-y-2 rounded-xl border border-gray-200 bg-white p-3">
                            {agents.length === 0 ? (
                                <p className="px-2 py-3 text-sm text-gray-500">No hay agentes aún.</p>
                            ) : (
                                agents.map((agent) => (
                                    <button
                                        key={agent.id}
                                        onClick={() => selectAgent(agent)}
                                        className={`w-full rounded-lg border px-3 py-2 text-left ${selectedId === agent.id ? 'border-ari-500 bg-ari-50' : 'border-gray-200 hover:bg-gray-50'}`}
                                    >
                                        <div className="flex items-center justify-between gap-2">
                                            <p className="truncate text-sm font-medium text-gray-900">{agent.name}</p>
                                            {agent.is_default && <Star className="h-4 w-4 text-amber-500" />}
                                        </div>
                                        <p className="mt-1 text-xs text-gray-500">{agent.is_enabled ? 'Activo' : 'Inactivo'}</p>
                                    </button>
                                ))
                            )}
                        </div>

                        <div className="space-y-4 lg:col-span-2">
                            {selectedAgent ? (
                                <>
                                    <form onSubmit={saveSelected} className="space-y-4 rounded-xl border border-gray-200 bg-white p-5">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <button
                                                type="button"
                                                onClick={toggleSelected}
                                                disabled={busy}
                                                className={`inline-flex min-h-11 items-center gap-2 rounded-lg border px-4 py-2 text-sm font-medium ${selectedAgent.is_enabled ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-gray-200 bg-white text-gray-700'} disabled:opacity-50`}
                                            >
                                                {busy ? <Loader2 className="h-4 w-4 animate-spin" /> : <Sparkles className="h-4 w-4" />}
                                                {selectedAgent.is_enabled ? 'Activo' : 'Inactivo'}
                                            </button>

                                            <button
                                                type="button"
                                                onClick={setDefaultSelected}
                                                disabled={busy || selectedAgent.is_default}
                                                className="inline-flex min-h-11 items-center gap-2 rounded-lg border border-amber-200 bg-amber-50 px-4 py-2 text-sm font-medium text-amber-700 disabled:opacity-50"
                                            >
                                                <Star className="h-4 w-4" />
                                                {selectedAgent.is_default ? 'Predeterminado' : 'Marcar predeterminado'}
                                            </button>

                                            <button
                                                type="button"
                                                onClick={deleteSelected}
                                                disabled={busy}
                                                className="inline-flex min-h-11 items-center gap-2 rounded-lg border border-red-200 bg-red-50 px-4 py-2 text-sm font-medium text-red-700 disabled:opacity-50"
                                            >
                                                <Trash2 className="h-4 w-4" />
                                                Eliminar
                                            </button>
                                        </div>

                                        <div>
                                            <label className="mb-1 block text-xs font-medium text-gray-700">Nombre del agente</label>
                                            <input
                                                value={form.name}
                                                onChange={(e) => setForm((prev) => ({ ...prev, name: e.target.value }))}
                                                className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-ari-500 focus:outline-none"
                                            />
                                            {errors.name && <p className="mt-1 text-xs text-red-500">{errors.name}</p>}
                                        </div>

                                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                            <div>
                                                <label className="mb-1 block text-xs font-medium text-gray-700">Modelo (Anthropic / OpenAI / Gemini)</label>
                                                <select
                                                    value={form.llm_model}
                                                    onChange={(e) => setForm((prev) => ({ ...prev, llm_model: e.target.value }))}
                                                    className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-ari-500 focus:outline-none"
                                                >
                                                    {availableModels.map((m) => (
                                                        <option key={m} value={m}>{m}</option>
                                                    ))}
                                                </select>
                                                {errors.llm_model && <p className="mt-1 text-xs text-red-500">{errors.llm_model}</p>}
                                            </div>

                                            <div>
                                                <label className="mb-1 block text-xs font-medium text-gray-700">Mensajes de contexto</label>
                                                <input
                                                    type="number"
                                                    min={3}
                                                    max={50}
                                                    value={form.context_messages}
                                                    onChange={(e) => setForm((prev) => ({ ...prev, context_messages: Number(e.target.value) }))}
                                                    className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-ari-500 focus:outline-none"
                                                />
                                                {errors.context_messages && <p className="mt-1 text-xs text-red-500">{errors.context_messages}</p>}
                                            </div>
                                        </div>

                                        <div>
                                            <label className="mb-1 block text-xs font-medium text-gray-700">System prompt</label>
                                            <textarea
                                                rows={12}
                                                value={form.system_prompt ?? ''}
                                                onChange={(e) => setForm((prev) => ({ ...prev, system_prompt: e.target.value }))}
                                                placeholder="Describe tono, límites y objetivo del agente..."
                                                className="w-full resize-y rounded-lg border border-gray-200 px-3 py-2 text-sm leading-relaxed focus:border-ari-500 focus:outline-none"
                                            />
                                            {errors.system_prompt && <p className="mt-1 text-xs text-red-500">{errors.system_prompt}</p>}
                                        </div>

                                        {errors.form && <p className="text-sm text-red-600">{errors.form}</p>}

                                        <div className="flex justify-end">
                                            <button
                                                type="submit"
                                                disabled={saving || busy}
                                                className="inline-flex min-h-11 items-center gap-2 rounded-lg bg-ari-600 px-4 py-2 text-sm font-medium text-white hover:bg-ari-700 disabled:opacity-50"
                                            >
                                                {saving ? <Loader2 className="h-4 w-4 animate-spin" /> : <Save className="h-4 w-4" />}
                                                Guardar configuración
                                            </button>
                                        </div>
                                    </form>

                                    <PlaygroundPanel
                                        agentName={selectedAgent.name}
                                        systemPromptIsEmpty={!String(form.system_prompt ?? '').trim()}
                                        messages={playgroundMessages}
                                        input={playgroundInput}
                                        sending={playgroundSending}
                                        apiStatus={apiStatus}
                                        apiError={apiError}
                                        onInputChange={setPlaygroundInput}
                                        onSend={sendPlayground}
                                        onClear={() => {
                                            setPlaygroundMessages([]);
                                            setApiStatus('unknown');
                                            setApiError(null);
                                        }}
                                    />
                                </>
                            ) : (
                                <div className="rounded-xl border border-gray-200 bg-white p-5 text-sm text-gray-500">
                                    Crea tu primer agente para empezar.
                                </div>
                            )}
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}

interface PlaygroundPanelProps {
    agentName: string;
    systemPromptIsEmpty: boolean;
    messages: PlaygroundMessage[];
    input: string;
    sending: boolean;
    apiStatus: 'unknown' | 'ok' | 'error';
    apiError: string | null;
    onInputChange: (value: string) => void;
    onSend: (e?: FormEvent) => void;
    onClear: () => void;
}

function PlaygroundPanel({
    agentName,
    systemPromptIsEmpty,
    messages,
    input,
    sending,
    apiStatus,
    apiError,
    onInputChange,
    onSend,
    onClear,
}: PlaygroundPanelProps) {
    const messagesEndRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        messagesEndRef.current?.scrollIntoView({ behavior: 'smooth', block: 'end' });
    }, [messages.length, sending]);

    const statusClasses = {
        unknown: 'bg-gray-100 text-gray-500',
        ok: 'bg-green-100 text-green-700',
        error: 'bg-red-100 text-red-700',
    }[apiStatus];

    const statusLabel = {
        unknown: 'API sin probar',
        ok: 'API OK',
        error: 'API con error',
    }[apiStatus];

    return (
        <section className="rounded-xl border border-gray-200 bg-white">
            <div className="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-5 py-4">
                <div>
                    <h2 className="text-base font-semibold text-gray-900">Probar agente</h2>
                    <p className="text-sm text-gray-500">{agentName}</p>
                </div>
                <div className="flex items-center gap-2">
                    <span className={`rounded-full px-2.5 py-1 text-xs font-medium ${statusClasses}`}>
                        {statusLabel}
                    </span>
                    <button
                        type="button"
                        onClick={onClear}
                        disabled={sending || messages.length === 0}
                        className="inline-flex min-h-9 items-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-50 disabled:opacity-50"
                    >
                        <RotateCcw className="h-3.5 w-3.5" />
                        Limpiar
                    </button>
                </div>
            </div>

            <div className="space-y-3 px-5 py-4">
                {systemPromptIsEmpty && (
                    <div className="flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800">
                        <AlertTriangle className="mt-0.5 h-4 w-4 flex-shrink-0" />
                        <span>El agente no tiene system prompt configurado.</span>
                    </div>
                )}

                {apiError && (
                    <div className="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
                        {apiError}
                    </div>
                )}

                <div className="max-h-96 min-h-64 space-y-3 overflow-y-auto rounded-lg bg-gray-50 px-3 py-3">
                    {messages.length === 0 && !sending ? (
                        <div className="flex h-52 items-center justify-center text-sm text-gray-400">
                            Escribe un mensaje para probar este agente.
                        </div>
                    ) : (
                        <>
                            {messages.map((message, index) => (
                                <ChatBubble
                                    key={`${message.role}-${index}`}
                                    role={message.role}
                                    content={message.content}
                                    meta={message.meta}
                                />
                            ))}
                            {sending && <ChatBubble role="assistant" content="" loading />}
                        </>
                    )}
                    <div ref={messagesEndRef} />
                </div>

                <form onSubmit={onSend} className="flex items-end gap-2">
                    <textarea
                        value={input}
                        onChange={(e) => onInputChange(e.target.value)}
                        onKeyDown={(e) => {
                            if (e.key === 'Enter' && !e.shiftKey) {
                                e.preventDefault();
                                onSend();
                            }
                        }}
                        rows={2}
                        disabled={sending}
                        placeholder="Escribe un mensaje de prueba..."
                        className="min-h-11 flex-1 resize-y rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-ari-500 focus:outline-none disabled:opacity-60"
                    />
                    <button
                        type="submit"
                        disabled={sending || !input.trim()}
                        className="inline-flex min-h-11 items-center gap-2 rounded-lg bg-ari-600 px-4 py-2 text-sm font-medium text-white hover:bg-ari-700 disabled:opacity-50"
                    >
                        {sending ? <Loader2 className="h-4 w-4 animate-spin" /> : <Send className="h-4 w-4" />}
                        Enviar
                    </button>
                </form>
            </div>
        </section>
    );
}

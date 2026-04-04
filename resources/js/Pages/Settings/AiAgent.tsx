import AppLayout from '@/Layouts/AppLayout';
import { Head } from '@inertiajs/react';
import axios from 'axios';
import { Loader2, Save, Sparkles } from 'lucide-react';
import { FormEvent, useEffect, useState } from 'react';

interface AiAgentConfig {
    id: string | null;
    name: string;
    system_prompt: string | null;
    llm_model: string;
    is_enabled: boolean;
    context_messages: number;
    is_configured: boolean;
}

export default function SettingsAiAgent() {
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [toggling, setToggling] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [availableModels, setAvailableModels] = useState<string[]>([]);
    const [form, setForm] = useState<AiAgentConfig>({
        id: null,
        name: 'Agente IA',
        system_prompt: '',
        llm_model: 'claude-haiku-4-5',
        is_enabled: false,
        context_messages: 10,
        is_configured: false,
    });

    async function loadConfig() {
        setLoading(true);
        try {
            const res = await axios.get<{ data: AiAgentConfig; available_models: string[] }>('/api/v1/ai-agent');
            setForm(res.data.data);
            setAvailableModels(res.data.available_models ?? []);
        } finally {
            setLoading(false);
        }
    }

    useEffect(() => {
        void loadConfig();
    }, []);

    async function handleToggle() {
        setToggling(true);
        try {
            const res = await axios.patch<{ data: AiAgentConfig; available_models: string[] }>('/api/v1/ai-agent/toggle', {
                enabled: !form.is_enabled,
            });
            setForm(prev => ({ ...prev, ...res.data.data }));
        } finally {
            setToggling(false);
        }
    }

    async function handleSubmit(e: FormEvent) {
        e.preventDefault();
        setSaving(true);
        setErrors({});
        try {
            const res = await axios.put<{ data: AiAgentConfig; available_models: string[] }>('/api/v1/ai-agent', {
                name: form.name,
                llm_model: form.llm_model,
                context_messages: form.context_messages,
                system_prompt: form.system_prompt,
                is_enabled: form.is_enabled,
            });
            setForm(prev => ({ ...prev, ...res.data.data }));
            setAvailableModels(res.data.available_models ?? availableModels);
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

    return (
        <AppLayout title="Agente IA">
            <Head title="Agente IA" />

            <div className="mx-auto max-w-3xl space-y-5 p-6">
                <div className="flex items-start justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold text-gray-900">Agente IA</h1>
                        <p className="mt-1 text-sm text-gray-500">
                            Configura el agente conversacional que responde automáticamente en WhatsApp.
                        </p>
                    </div>

                    <button
                        onClick={handleToggle}
                        disabled={loading || toggling}
                        className={`inline-flex min-h-11 items-center gap-2 rounded-lg border px-4 py-2 text-sm font-medium ${form.is_enabled ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-gray-200 bg-white text-gray-700'} disabled:opacity-50`}
                    >
                        {(loading || toggling) ? <Loader2 className="h-4 w-4 animate-spin" /> : <Sparkles className="h-4 w-4" />}
                        {form.is_enabled ? 'Agente activo' : 'Agente inactivo'}
                    </button>
                </div>

                {loading ? (
                    <div className="flex h-52 items-center justify-center rounded-xl border border-gray-200 bg-white">
                        <Loader2 className="h-6 w-6 animate-spin text-ari-600" />
                    </div>
                ) : (
                    <form onSubmit={handleSubmit} className="space-y-4 rounded-xl border border-gray-200 bg-white p-5">
                        <div>
                            <label className="mb-1 block text-xs font-medium text-gray-700">Nombre del agente</label>
                            <input
                                value={form.name}
                                onChange={(e) => setForm(prev => ({ ...prev, name: e.target.value }))}
                                className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-ari-500 focus:outline-none"
                            />
                            {errors.name && <p className="mt-1 text-xs text-red-500">{errors.name}</p>}
                        </div>

                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div>
                                <label className="mb-1 block text-xs font-medium text-gray-700">Modelo (Anthropic / OpenAI / Gemini)</label>
                                <select
                                    value={form.llm_model}
                                    onChange={(e) => setForm(prev => ({ ...prev, llm_model: e.target.value }))}
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
                                    onChange={(e) => setForm(prev => ({ ...prev, context_messages: Number(e.target.value) }))}
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
                                onChange={(e) => setForm(prev => ({ ...prev, system_prompt: e.target.value }))}
                                placeholder="Describe tono, límites y objetivo del agente..."
                                className="w-full resize-y rounded-lg border border-gray-200 px-3 py-2 text-sm leading-relaxed focus:border-ari-500 focus:outline-none"
                            />
                            {errors.system_prompt && <p className="mt-1 text-xs text-red-500">{errors.system_prompt}</p>}
                        </div>

                        <div className="flex justify-end">
                            <button
                                type="submit"
                                disabled={saving}
                                className="inline-flex min-h-11 items-center gap-2 rounded-lg bg-ari-600 px-4 py-2 text-sm font-medium text-white hover:bg-ari-700 disabled:opacity-50"
                            >
                                {saving ? <Loader2 className="h-4 w-4 animate-spin" /> : <Save className="h-4 w-4" />}
                                Guardar configuración
                            </button>
                        </div>
                    </form>
                )}
            </div>
        </AppLayout>
    );
}

import AppLayout from '@/Layouts/AppLayout';
import { Head, usePage } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import { PageProps, BusinessHourDay } from '@/types';
import { Save, Clock, Globe, Timer, Plus, X } from 'lucide-react';
import axios from 'axios';

const DAYS = [
    { key: 'monday',    label: 'Lunes' },
    { key: 'tuesday',   label: 'Martes' },
    { key: 'wednesday', label: 'Miércoles' },
    { key: 'thursday',  label: 'Jueves' },
    { key: 'friday',    label: 'Viernes' },
    { key: 'saturday',  label: 'Sábado' },
    { key: 'sunday',    label: 'Domingo' },
];

const DEFAULT_HOURS: Record<string, BusinessHourDay> = Object.fromEntries(
    DAYS.map(({ key }) => [
        key,
        { enabled: !['saturday', 'sunday'].includes(key), blocks: [{ start: '08:00', end: '18:00' }] },
    ])
);

interface TenantSettings {
    timezone: string;
    auto_close_hours: number | null;
    business_hours: Record<string, BusinessHourDay>;
    max_agents: number | null;
    max_contacts: number | null;
}

export default function SettingsGeneral() {
    const { auth } = usePage<PageProps>().props;
    const isOwner = auth.user.role === 'owner';

    const [settings, setSettings] = useState<TenantSettings | null>(null);
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [saved, setSaved] = useState(false);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        axios.get('/api/v1/tenant/settings')
            .then(res => {
                const data = res.data.data as TenantSettings;
                setSettings({
                    ...data,
                    business_hours: data.business_hours ?? DEFAULT_HOURS,
                });
            })
            .catch(() => setError('No se pudo cargar la configuración.'))
            .finally(() => setLoading(false));
    }, []);

    const handleSave = async () => {
        if (!settings || !isOwner) return;
        setSaving(true);
        setError(null);
        try {
            await axios.patch('/api/v1/tenant/settings', settings);
            setSaved(true);
            setTimeout(() => setSaved(false), 3000);
        } catch (err: any) {
            setError(err.response?.data?.message ?? 'Error al guardar.');
        } finally {
            setSaving(false);
        }
    };

    const toggleDay = (day: string, enabled: boolean) => {
        if (!settings) return;
        setSettings(prev => ({
            ...prev!,
            business_hours: {
                ...prev!.business_hours,
                [day]: { ...prev!.business_hours[day], enabled },
            },
        }));
    };

    const updateBlock = (day: string, index: number, field: 'start' | 'end', value: string) => {
        if (!settings) return;
        setSettings(prev => {
            const blocks = [...prev!.business_hours[day].blocks];
            blocks[index] = { ...blocks[index], [field]: value };
            return {
                ...prev!,
                business_hours: {
                    ...prev!.business_hours,
                    [day]: { ...prev!.business_hours[day], blocks },
                },
            };
        });
    };

    const addBlock = (day: string) => {
        if (!settings) return;
        setSettings(prev => ({
            ...prev!,
            business_hours: {
                ...prev!.business_hours,
                [day]: {
                    ...prev!.business_hours[day],
                    blocks: [...prev!.business_hours[day].blocks, { start: '08:00', end: '18:00' }],
                },
            },
        }));
    };

    const removeBlock = (day: string, index: number) => {
        if (!settings) return;
        setSettings(prev => {
            const blocks = prev!.business_hours[day].blocks.filter((_, i) => i !== index);
            return {
                ...prev!,
                business_hours: {
                    ...prev!.business_hours,
                    [day]: { ...prev!.business_hours[day], blocks },
                },
            };
        });
    };

    if (loading) {
        return (
            <AppLayout title="Configuración general">
                <Head title="Configuración general" />
                <div className="p-6 space-y-4">
                    {[1, 2, 3].map(i => (
                        <div key={i} className="h-20 animate-pulse rounded-xl bg-gray-200" />
                    ))}
                </div>
            </AppLayout>
        );
    }

    return (
        <AppLayout title="Configuración general">
            <Head title="Configuración general" />

            <div className="max-w-2xl space-y-6 p-6">
                <div>
                    <h1 className="text-2xl font-semibold text-gray-900">Configuración general</h1>
                    <p className="mt-1 text-sm text-gray-500">
                        Zona horaria, horario laboral y cierre automático de conversaciones.
                    </p>
                </div>

                {error && (
                    <div className="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                        {error}
                    </div>
                )}

                {/* Zona horaria */}
                <section className="rounded-xl border border-gray-200 bg-white p-5">
                    <div className="mb-4 flex items-center gap-2">
                        <Globe className="h-5 w-5 text-ari-600" />
                        <h2 className="text-base font-semibold text-gray-900">Zona horaria</h2>
                    </div>
                    <select
                        disabled={!isOwner}
                        value={settings?.timezone ?? 'America/Bogota'}
                        onChange={e => setSettings(prev => ({ ...prev!, timezone: e.target.value }))}
                        className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-ari-500 focus:outline-none focus:ring-1 focus:ring-ari-500 disabled:bg-gray-50 disabled:text-gray-500"
                    >
                        {[
                            'America/Bogota', 'America/Lima', 'America/Caracas',
                            'America/Mexico_City', 'America/Santiago',
                            'America/Buenos_Aires', 'America/Sao_Paulo', 'America/New_York',
                            'Europe/Madrid', 'UTC',
                        ].map(tz => (
                            <option key={tz} value={tz}>{tz}</option>
                        ))}
                    </select>
                    {!isOwner && (
                        <p className="mt-2 text-xs text-gray-400">Solo el propietario puede cambiar la zona horaria.</p>
                    )}
                </section>

                {/* Cierre automático */}
                <section className="rounded-xl border border-gray-200 bg-white p-5">
                    <div className="mb-4 flex items-center gap-2">
                        <Timer className="h-5 w-5 text-ari-600" />
                        <h2 className="text-base font-semibold text-gray-900">Cierre automático</h2>
                    </div>
                    <p className="mb-3 text-sm text-gray-500">
                        Cierra automáticamente conversaciones sin actividad después de X horas. Deja vacío para desactivar.
                    </p>
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                        <input
                            type="number"
                            disabled={!isOwner}
                            min={1}
                            max={8760}
                            value={settings?.auto_close_hours ?? ''}
                            onChange={e => setSettings(prev => ({
                                ...prev!,
                                auto_close_hours: e.target.value ? parseInt(e.target.value) : null,
                            }))}
                            placeholder="Sin cierre automático"
                            className="min-w-0 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-ari-500 focus:outline-none focus:ring-1 focus:ring-ari-500 disabled:bg-gray-50 sm:w-48"
                        />
                        <span className="text-sm text-gray-500">horas</span>
                    </div>
                </section>

                {/* Horario laboral */}
                <section className="rounded-xl border border-gray-200 bg-white p-5">
                    <div className="mb-4 flex items-center gap-2">
                        <Clock className="h-5 w-5 text-ari-600" />
                        <h2 className="text-base font-semibold text-gray-900">Horario laboral</h2>
                    </div>
                    <p className="mb-4 text-sm text-gray-500">
                        Usado para calcular el Dt1 y disparar automatizaciones fuera de horario.
                    </p>
                    <div className="space-y-4">
                        {DAYS.map(({ key, label }) => {
                            const day = settings?.business_hours?.[key] ?? { enabled: false, blocks: [{ start: '08:00', end: '18:00' }] };
                            return (
                                <div key={key} className="rounded-lg border border-gray-100 p-3">
                                    {/* Day toggle */}
                                    <div className="flex items-center gap-2">
                                        <input
                                            type="checkbox"
                                            id={`day-${key}`}
                                            disabled={!isOwner}
                                            checked={day.enabled}
                                            onChange={e => toggleDay(key, e.target.checked)}
                                            className="h-4 w-4 rounded border-gray-300 text-ari-600 focus:ring-ari-500"
                                        />
                                        <label htmlFor={`day-${key}`} className="w-24 text-sm font-medium text-gray-700">
                                            {label}
                                        </label>
                                        {!day.enabled && (
                                            <span className="text-xs text-gray-400">Cerrado</span>
                                        )}
                                    </div>

                                    {/* Blocks */}
                                    {day.enabled && (
                                        <div className="mt-2 ml-6 space-y-2">
                                            {day.blocks.map((block, i) => (
                                                <div key={i} className="flex items-center gap-2">
                                                    <input
                                                        type="time"
                                                        disabled={!isOwner}
                                                        value={block.start}
                                                        onChange={e => updateBlock(key, i, 'start', e.target.value)}
                                                        className="w-28 rounded-lg border border-gray-300 px-2 py-1.5 text-sm focus:border-ari-500 focus:outline-none disabled:bg-gray-50 disabled:text-gray-400"
                                                    />
                                                    <span className="text-sm text-gray-400">—</span>
                                                    <input
                                                        type="time"
                                                        disabled={!isOwner}
                                                        value={block.end}
                                                        onChange={e => updateBlock(key, i, 'end', e.target.value)}
                                                        className="w-28 rounded-lg border border-gray-300 px-2 py-1.5 text-sm focus:border-ari-500 focus:outline-none disabled:bg-gray-50 disabled:text-gray-400"
                                                    />
                                                    {isOwner && day.blocks.length > 1 && (
                                                        <button
                                                            onClick={() => removeBlock(key, i)}
                                                            className="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600"
                                                            title="Eliminar franja"
                                                        >
                                                            <X className="h-3.5 w-3.5" />
                                                        </button>
                                                    )}
                                                </div>
                                            ))}

                                            {isOwner && (
                                                <button
                                                    onClick={() => addBlock(key)}
                                                    className="flex items-center gap-1 text-xs text-ari-600 hover:text-ari-700"
                                                >
                                                    <Plus className="h-3 w-3" />
                                                    Agregar franja
                                                </button>
                                            )}
                                        </div>
                                    )}
                                </div>
                            );
                        })}
                    </div>
                </section>

                {/* Plan limits (read-only) */}
                {(settings?.max_agents || settings?.max_contacts) && (
                    <section className="rounded-xl border border-gray-200 bg-white p-5">
                        <h2 className="mb-3 text-base font-semibold text-gray-900">Límites del plan</h2>
                        <div className="flex flex-col gap-2 text-sm text-gray-600 sm:flex-row sm:gap-6">
                            {settings.max_agents && (
                                <div>
                                    <span className="font-medium text-gray-900">{settings.max_agents}</span> agentes máx.
                                </div>
                            )}
                            {settings.max_contacts && (
                                <div>
                                    <span className="font-medium text-gray-900">{settings.max_contacts.toLocaleString()}</span> contactos máx.
                                </div>
                            )}
                        </div>
                    </section>
                )}

                {isOwner && (
                    <div className="flex flex-col gap-2 sm:flex-row sm:items-center">
                        <button
                            onClick={handleSave}
                            disabled={saving}
                            className="flex min-h-11 w-full items-center justify-center gap-2 rounded-lg bg-ari-600 px-5 py-2 text-sm font-medium text-white hover:bg-ari-700 disabled:opacity-60 sm:w-auto"
                        >
                            <Save className="h-4 w-4" />
                            {saving ? 'Guardando...' : 'Guardar cambios'}
                        </button>
                        {saved && <span className="text-sm text-green-600">¡Guardado correctamente!</span>}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}

import AppLayout from '@/Layouts/AppLayout';
import { Head } from '@inertiajs/react';
import axios from 'axios';
import { Loader2, Plus, Save } from 'lucide-react';
import { FormEvent, useEffect, useState } from 'react';

interface BookableUnit {
    id: string;
    type: string;
    name: string;
    capacity: number;
    settings: { services?: string[]; slug?: string } | null;
    is_active: boolean;
}

interface FormState {
    type: string;
    name: string;
    capacity: number;
    services: string;
    is_active: boolean;
}

const emptyForm: FormState = {
    type: 'professional',
    name: '',
    capacity: 1,
    services: '',
    is_active: true,
};

export default function BookableUnitsIndex() {
    const [units, setUnits] = useState<BookableUnit[]>([]);
    const [selectedId, setSelectedId] = useState<string | null>(null);
    const [form, setForm] = useState<FormState>(emptyForm);
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);

    async function loadUnits() {
        setLoading(true);
        try {
            const res = await axios.get<{ data: BookableUnit[] }>('/api/v1/bookable-units');
            setUnits(res.data.data ?? []);
        } finally {
            setLoading(false);
        }
    }

    useEffect(() => {
        void loadUnits();
    }, []);

    function selectUnit(unit: BookableUnit) {
        setSelectedId(unit.id);
        setForm({
            type: unit.type,
            name: unit.name,
            capacity: unit.capacity,
            services: unit.settings?.services?.join(', ') ?? '',
            is_active: unit.is_active,
        });
    }

    async function submit(e: FormEvent) {
        e.preventDefault();
        setSaving(true);
        const payload = {
            type: form.type,
            name: form.name,
            capacity: form.capacity,
            is_active: form.is_active,
            settings: {
                services: form.services.split(',').map((item) => item.trim()).filter(Boolean),
                slug: form.name.toLowerCase().trim().replace(/\s+/g, '-'),
            },
        };

        try {
            if (selectedId) {
                await axios.put(`/api/v1/bookable-units/${selectedId}`, payload);
            } else {
                await axios.post('/api/v1/bookable-units', payload);
            }
            setSelectedId(null);
            setForm(emptyForm);
            await loadUnits();
        } finally {
            setSaving(false);
        }
    }

    return (
        <AppLayout title="Recursos">
            <Head title="Recursos" />
            <div className="space-y-5 p-4 md:p-6">
                <div className="flex items-center justify-between rounded-lg border border-gray-200 bg-white p-4">
                    <div>
                        <h1 className="text-xl font-semibold text-gray-900">Recursos reservables</h1>
                        <p className="text-sm text-gray-500">Profesionales, salas, mesas o equipos disponibles para reservas.</p>
                    </div>
                    <button
                        onClick={() => {
                            setSelectedId(null);
                            setForm(emptyForm);
                        }}
                        className="inline-flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                    >
                        <Plus className="h-4 w-4" />
                        Nuevo
                    </button>
                </div>

                <div className="grid gap-4 lg:grid-cols-3">
                    <div className="rounded-lg border border-gray-200 bg-white p-3">
                        {loading ? (
                            <div className="flex h-40 items-center justify-center"><Loader2 className="h-5 w-5 animate-spin text-ari-600" /></div>
                        ) : (
                            <div className="space-y-2">
                                {units.map((unit) => (
                                    <button
                                        key={unit.id}
                                        onClick={() => selectUnit(unit)}
                                        className={`w-full rounded-lg border px-3 py-2 text-left text-sm ${selectedId === unit.id ? 'border-ari-500 bg-ari-50' : 'border-gray-200 hover:bg-gray-50'}`}
                                    >
                                        <p className="font-medium text-gray-900">{unit.name}</p>
                                        <p className="text-xs text-gray-500">{unit.type} · {unit.is_active ? 'Activo' : 'Inactivo'}</p>
                                    </button>
                                ))}
                            </div>
                        )}
                    </div>

                    <form onSubmit={submit} className="space-y-4 rounded-lg border border-gray-200 bg-white p-4 lg:col-span-2">
                        <div className="grid gap-3 sm:grid-cols-2">
                            <div>
                                <label className="mb-1 block text-xs font-medium text-gray-700">Nombre</label>
                                <input value={form.name} onChange={(e) => setForm((p) => ({ ...p, name: e.target.value }))} className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-ari-500 focus:outline-none" required />
                            </div>
                            <div>
                                <label className="mb-1 block text-xs font-medium text-gray-700">Tipo</label>
                                <input value={form.type} onChange={(e) => setForm((p) => ({ ...p, type: e.target.value }))} className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-ari-500 focus:outline-none" required />
                            </div>
                        </div>
                        <div className="grid gap-3 sm:grid-cols-2">
                            <div>
                                <label className="mb-1 block text-xs font-medium text-gray-700">Capacidad</label>
                                <input type="number" min={1} value={form.capacity} onChange={(e) => setForm((p) => ({ ...p, capacity: Number(e.target.value) }))} className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-ari-500 focus:outline-none" />
                            </div>
                            <label className="flex items-center gap-2 pt-6 text-sm text-gray-700">
                                <input type="checkbox" checked={form.is_active} onChange={(e) => setForm((p) => ({ ...p, is_active: e.target.checked }))} />
                                Activo
                            </label>
                        </div>
                        <div>
                            <label className="mb-1 block text-xs font-medium text-gray-700">Servicios</label>
                            <input value={form.services} onChange={(e) => setForm((p) => ({ ...p, services: e.target.value }))} placeholder="citologia, consulta_ginecologica" className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-ari-500 focus:outline-none" />
                        </div>
                        <div className="flex justify-end">
                            <button disabled={saving} className="inline-flex items-center gap-2 rounded-lg bg-ari-600 px-4 py-2 text-sm font-medium text-white hover:bg-ari-700 disabled:opacity-50">
                                {saving ? <Loader2 className="h-4 w-4 animate-spin" /> : <Save className="h-4 w-4" />}
                                Guardar
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </AppLayout>
    );
}

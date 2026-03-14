import AppLayout from '@/Layouts/AppLayout';
import { Contact } from '@/types';
import { Head, Link } from '@inertiajs/react';
import axios from 'axios';
import { ArrowLeft, ArrowLeftRight, Check, Loader2, Users } from 'lucide-react';
import { useEffect, useState } from 'react';

interface DuplicateGroup {
    normalized_phone: string;
    contacts: Contact[];
}

function displayName(c: Contact): string {
    return c.name || c.push_name || c.phone || 'Desconocido';
}

function ContactCard({ contact, label, labelClass }: { contact: Contact; label: string; labelClass: string }) {
    return (
        <div className={`flex-1 rounded-lg border p-4 ${labelClass}`}>
            <span className="mb-2 inline-block rounded-full px-2 py-0.5 text-xs font-medium">{label}</span>
            <p className="font-medium text-gray-900">{displayName(contact)}</p>
            <p className="mt-0.5 text-xs text-gray-500">{contact.phone}</p>
            <div className="mt-1 flex flex-wrap gap-x-2 text-xs text-gray-400">
                <span>{contact.source === 'manual' ? 'Manual' : 'WhatsApp'}</span>
                {contact.wa_id && <span>· Con WA</span>}
                {contact.email && <span>· {contact.email}</span>}
                {contact.company && <span>· {contact.company}</span>}
                {contact.assignee && <span>· {contact.assignee.name}</span>}
            </div>
        </div>
    );
}

export default function DataQuality() {
    const [groups, setGroups] = useState<DuplicateGroup[]>([]);
    const [loading, setLoading] = useState(true);
    const [swapped, setSwapped] = useState<Record<string, boolean>>({});
    const [merging, setMerging] = useState<string | null>(null);
    const [done, setDone] = useState<Set<string>>(new Set());

    useEffect(() => {
        axios
            .get<{ data: DuplicateGroup[] }>('/api/v1/contacts/duplicates')
            .then((res) => setGroups(res.data.data))
            .finally(() => setLoading(false));
    }, []);

    async function handleMerge(group: DuplicateGroup) {
        const isSwapped = swapped[group.normalized_phone] ?? false;
        const [first, second] = group.contacts;
        const target = isSwapped ? second : first;
        const source = isSwapped ? first : second;

        setMerging(group.normalized_phone);
        try {
            await axios.post(`/api/v1/contacts/${source.id}/merge`, { merge_into_id: target.id });
            setDone((prev) => new Set([...prev, group.normalized_phone]));
        } finally {
            setMerging(null);
        }
    }

    const pending = groups.filter((g) => !done.has(g.normalized_phone));

    return (
        <AppLayout title="Calidad de datos">
            <Head title="Calidad de datos" />

            <div className="space-y-5 p-6">
                <div className="flex items-center gap-3">
                    <Link href="/settings" className="text-gray-400 hover:text-gray-600">
                        <ArrowLeft className="h-5 w-5" />
                    </Link>
                    <div>
                        <h1 className="text-2xl font-semibold text-gray-900">Calidad de datos</h1>
                        <p className="mt-1 text-sm text-gray-500">
                            Detecta y fusiona contactos duplicados que comparten el mismo número de teléfono.
                        </p>
                    </div>
                </div>

                {loading ? (
                    <div className="flex items-center gap-2 text-sm text-gray-500">
                        <Loader2 className="h-4 w-4 animate-spin" />
                        Buscando duplicados…
                    </div>
                ) : pending.length === 0 ? (
                    <div className="rounded-xl border border-gray-200 bg-white p-10 text-center">
                        <Users className="mx-auto h-10 w-10 text-gray-300" />
                        <p className="mt-3 text-sm font-medium text-gray-700">Sin duplicados</p>
                        <p className="mt-1 text-sm text-gray-400">
                            No se encontraron contactos con el mismo teléfono.
                        </p>
                    </div>
                ) : (
                    <div className="space-y-3">
                        <p className="text-sm text-gray-500">
                            {pending.length} {pending.length === 1 ? 'par encontrado' : 'pares encontrados'} —
                            elige cuál mantener y fusiona.
                        </p>

                        {pending.map((group) => {
                            const isSwapped = swapped[group.normalized_phone] ?? false;
                            const [first, second] = group.contacts;
                            const target = isSwapped ? second : first;
                            const source = isSwapped ? first : second;
                            const isMerging = merging === group.normalized_phone;

                            return (
                                <div
                                    key={group.normalized_phone}
                                    className="rounded-xl border border-gray-200 bg-white p-5"
                                >
                                    <div className="flex items-start gap-3">
                                        <ContactCard
                                            contact={target}
                                            label="Mantener"
                                            labelClass="border-green-200 bg-green-50 [&>span]:bg-green-100 [&>span]:text-green-700"
                                        />

                                        <button
                                            onClick={() =>
                                                setSwapped((prev) => ({
                                                    ...prev,
                                                    [group.normalized_phone]: !prev[group.normalized_phone],
                                                }))
                                            }
                                            title="Intercambiar"
                                            className="mt-8 shrink-0 rounded-lg border border-gray-200 p-2 text-gray-400 hover:border-gray-300 hover:text-gray-600"
                                        >
                                            <ArrowLeftRight className="h-4 w-4" />
                                        </button>

                                        <ContactCard
                                            contact={source}
                                            label="Fusionar y eliminar"
                                            labelClass="border-red-100 bg-red-50 [&>span]:bg-red-100 [&>span]:text-red-600"
                                        />
                                    </div>

                                    <p className="mt-3 text-xs text-gray-400">
                                        Las conversaciones y deals del contacto eliminado se transferirán al que se mantiene.
                                    </p>

                                    <div className="mt-3 flex justify-end">
                                        <button
                                            onClick={() => handleMerge(group)}
                                            disabled={isMerging}
                                            className="inline-flex items-center gap-1.5 rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700 disabled:opacity-50"
                                        >
                                            {isMerging ? (
                                                <Loader2 className="h-3.5 w-3.5 animate-spin" />
                                            ) : (
                                                <Check className="h-3.5 w-3.5" />
                                            )}
                                            Unir contactos
                                        </button>
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}

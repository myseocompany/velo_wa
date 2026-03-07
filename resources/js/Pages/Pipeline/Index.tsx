import AppLayout from '@/Layouts/AppLayout';
import { DealStage, PaginatedData, PipelineDeal } from '@/types';
import { Head } from '@inertiajs/react';
import axios from 'axios';
import { BriefcaseBusiness } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

interface PipelineDealsApiResponse extends PaginatedData<PipelineDeal> {}

const STAGES: Array<{ key: DealStage; label: string }> = [
    { key: 'lead', label: 'Lead' },
    { key: 'qualified', label: 'Calificado' },
    { key: 'proposal', label: 'Propuesta' },
    { key: 'negotiation', label: 'Negociación' },
    { key: 'closed_won', label: 'Ganado' },
    { key: 'closed_lost', label: 'Perdido' },
];

export default function PipelineIndex() {
    const [deals, setDeals] = useState<PipelineDeal[]>([]);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        async function fetchDeals() {
            setLoading(true);
            try {
                const res = await axios.get<PipelineDealsApiResponse>('/api/v1/pipeline/deals', {
                    params: { per_page: 200 },
                });
                setDeals(res.data.data);
            } finally {
                setLoading(false);
            }
        }

        fetchDeals();
    }, []);

    const groupedDeals = useMemo(() => {
        return STAGES.reduce<Record<DealStage, PipelineDeal[]>>((acc, stage) => {
            acc[stage.key] = deals.filter((deal) => deal.stage === stage.key);
            return acc;
        }, {
            lead: [],
            qualified: [],
            proposal: [],
            negotiation: [],
            closed_won: [],
            closed_lost: [],
        });
    }, [deals]);

    return (
        <AppLayout title="Pipeline">
            <Head title="Pipeline" />

            <div className="space-y-5 p-6">
                <div>
                    <h1 className="text-2xl font-semibold text-gray-900">Pipeline</h1>
                    <p className="mt-1 text-sm text-gray-500">
                        Vista por etapas de oportunidades comerciales.
                    </p>
                </div>

                {loading ? (
                    <div className="flex h-48 items-center justify-center rounded-xl border border-gray-200 bg-white">
                        <div className="h-6 w-6 animate-spin rounded-full border-2 border-green-600 border-t-transparent" />
                    </div>
                ) : (
                    <div className="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                        {STAGES.map((stage) => (
                            <section key={stage.key} className="rounded-xl border border-gray-200 bg-white">
                                <header className="flex items-center justify-between border-b border-gray-100 px-4 py-3">
                                    <h2 className="text-sm font-semibold text-gray-900">{stage.label}</h2>
                                    <span className="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600">
                                        {groupedDeals[stage.key].length}
                                    </span>
                                </header>
                                <div className="space-y-2 p-3">
                                    {groupedDeals[stage.key].map((deal) => (
                                        <article key={deal.id} className="rounded-lg border border-gray-200 bg-gray-50 p-3">
                                            <p className="text-sm font-medium text-gray-900">{deal.title}</p>
                                            <p className="mt-1 text-xs text-gray-500">
                                                {deal.contact?.name ?? deal.contact?.push_name ?? deal.contact?.phone ?? 'Sin contacto'}
                                            </p>
                                            <p className="mt-2 text-xs font-medium text-gray-700">
                                                {deal.value ? `${deal.currency} ${Number(deal.value).toLocaleString('es-CO')}` : 'Sin valor'}
                                            </p>
                                        </article>
                                    ))}
                                    {!groupedDeals[stage.key].length && (
                                        <div className="flex items-center gap-2 rounded-lg border border-dashed border-gray-200 px-3 py-4 text-xs text-gray-400">
                                            <BriefcaseBusiness className="h-4 w-4" />
                                            <span>Sin deals en esta etapa</span>
                                        </div>
                                    )}
                                </div>
                            </section>
                        ))}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}

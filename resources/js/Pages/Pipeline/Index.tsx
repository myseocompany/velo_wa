import AppLayout from '@/Layouts/AppLayout';
import { DealStage, PaginatedData, PipelineDeal } from '@/types';
import { Head } from '@inertiajs/react';
import { DragDropContext, Draggable, Droppable, DropResult } from '@hello-pangea/dnd';
import axios from 'axios';
import { BriefcaseBusiness, CircleAlert, GripVertical } from 'lucide-react';
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

const STAGE_BADGE: Record<DealStage, string> = {
    lead: 'bg-slate-100 text-slate-700 ring-1 ring-slate-200',
    qualified: 'bg-sky-100 text-sky-700 ring-1 ring-sky-200',
    proposal: 'bg-amber-100 text-amber-800 ring-1 ring-amber-200',
    negotiation: 'bg-violet-100 text-violet-700 ring-1 ring-violet-200',
    closed_won: 'bg-emerald-100 text-emerald-700 ring-1 ring-emerald-200',
    closed_lost: 'bg-rose-100 text-rose-700 ring-1 ring-rose-200',
};

const STAGE_PANEL: Record<DealStage, string> = {
    lead: 'border-slate-200 bg-slate-50',
    qualified: 'border-sky-200 bg-sky-50',
    proposal: 'border-amber-200 bg-amber-50',
    negotiation: 'border-violet-200 bg-violet-50',
    closed_won: 'border-emerald-200 bg-emerald-50',
    closed_lost: 'border-rose-200 bg-rose-50',
};

type BoardState = Record<DealStage, PipelineDeal[]>;

function createEmptyBoard(): BoardState {
    return {
        lead: [],
        qualified: [],
        proposal: [],
        negotiation: [],
        closed_won: [],
        closed_lost: [],
    };
}

function groupDealsByStage(items: PipelineDeal[]): BoardState {
    return items.reduce<BoardState>((acc, deal) => {
        acc[deal.stage].push(deal);
        return acc;
    }, createEmptyBoard());
}

export default function PipelineIndex() {
    const [board, setBoard] = useState<BoardState>(createEmptyBoard);
    const [loading, setLoading] = useState(true);
    const [movingDealId, setMovingDealId] = useState<string | null>(null);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        async function fetchDeals() {
            setLoading(true);
            setError(null);
            try {
                const res = await axios.get<PipelineDealsApiResponse>('/api/v1/pipeline/deals', {
                    params: { per_page: 200 },
                });
                setBoard(groupDealsByStage(res.data.data));
            } catch {
                setError('No se pudo cargar el pipeline.');
            } finally {
                setLoading(false);
            }
        }

        fetchDeals();
    }, []);

    const totalsByStage = useMemo(
        () =>
            STAGES.reduce<Record<DealStage, number>>((acc, stage) => {
                acc[stage.key] = board[stage.key].length;
                return acc;
            }, {
                lead: 0,
                qualified: 0,
                proposal: 0,
                negotiation: 0,
                closed_won: 0,
                closed_lost: 0,
            }),
        [board],
    );

    const onDragEnd = async (result: DropResult) => {
        const { destination, source, draggableId } = result;

        if (!destination) {
            return;
        }

        const sourceStage = source.droppableId as DealStage;
        const destinationStage = destination.droppableId as DealStage;

        if (sourceStage === destinationStage && source.index === destination.index) {
            return;
        }

        setError(null);

        const previousBoard = structuredClone(board);
        const nextBoard = structuredClone(board);
        const sourceItems = nextBoard[sourceStage];
        const destinationItems = nextBoard[destinationStage];
        const [movedDeal] = sourceItems.splice(source.index, 1);

        if (!movedDeal) {
            return;
        }

        movedDeal.stage = destinationStage;
        destinationItems.splice(destination.index, 0, movedDeal);
        setBoard(nextBoard);
        setMovingDealId(draggableId);

        try {
            const res = await axios.patch<{ data: PipelineDeal }>(
                `/api/v1/pipeline/deals/${draggableId}/stage`,
                { stage: destinationStage },
            );
            const updatedDeal = res.data.data;
            setBoard((currentBoard) => {
                const syncedBoard = structuredClone(currentBoard);
                const targetItems = syncedBoard[destinationStage];
                const itemIndex = targetItems.findIndex((item) => item.id === updatedDeal.id);

                if (itemIndex >= 0) {
                    targetItems[itemIndex] = { ...targetItems[itemIndex], ...updatedDeal };
                }

                return syncedBoard;
            });
        } catch {
            setBoard(previousBoard);
            setError('No se pudo mover el deal. Intenta nuevamente.');
        } finally {
            setMovingDealId(null);
        }
    };

    return (
        <AppLayout title="Pipeline">
            <Head title="Pipeline" />

            <div className="min-h-[calc(100vh-4rem)] bg-[#f6f8fc] p-4 md:p-6">
                <div className="mx-auto flex h-full max-w-[1600px] flex-col">
                    <div className="rounded-xl border border-gray-300 bg-[#ececed] px-4 py-4 shadow-sm">
                        <h1 className="text-[30px] font-semibold tracking-tight text-gray-900">Pipeline Board</h1>
                        <p className="mt-1 text-sm text-gray-600">
                            Mueve tarjetas entre listas para actualizar su estado comercial.
                        </p>
                    </div>

                    {error && (
                        <div className="mt-3 flex items-center gap-2 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700">
                            <CircleAlert className="h-4 w-4" />
                            <span>{error}</span>
                        </div>
                    )}
                
                    {loading ? (
                    <div className="mt-4 flex h-48 items-center justify-center rounded-xl border border-slate-200 bg-white">
                        <div className="h-6 w-6 animate-spin rounded-full border-2 border-brand-600 border-t-transparent" />
                    </div>
                ) : (
                    <DragDropContext onDragEnd={onDragEnd}>
                        <div className="pipeline-scroll mt-4 min-h-0 flex-1 overflow-x-auto overflow-y-hidden pb-3">
                            <div className="flex min-w-max gap-3">
                                {STAGES.map((stage, stageIndex) => (
                                    <section
                                        key={stage.key}
                                        className={`w-[320px] shrink-0 rounded-xl border shadow-sm ${STAGE_PANEL[stage.key]}`}
                                    >
                                        <header className="border-b border-black/10 bg-white/80 px-3 py-3 backdrop-blur">
                                            <div className="flex items-center justify-between gap-2">
                                                <div className="flex items-center gap-2">
                                                    <span className="font-mono text-xs text-gray-500">{String(stageIndex + 1).padStart(2, '0')}</span>
                                                    <h2 className="text-sm font-semibold text-gray-900">{stage.label}</h2>
                                                </div>
                                                <span className={`rounded-md px-2 py-0.5 text-xs font-medium ${STAGE_BADGE[stage.key]}`}>
                                                    {totalsByStage[stage.key]}
                                                </span>
                                            </div>
                                        </header>

                                        <Droppable droppableId={stage.key}>
                                            {(provided, snapshot) => (
                                                <div
                                                    ref={provided.innerRef}
                                                    {...provided.droppableProps}
                                                    className={`min-h-44 space-y-2 p-2 transition ${
                                                        snapshot.isDraggingOver ? 'bg-white/80' : ''
                                                    }`}
                                                >
                                                    {board[stage.key].map((deal, index) => (
                                                        <Draggable key={deal.id} draggableId={deal.id} index={index}>
                                                            {(dragProvided, dragSnapshot) => (
                                                                <article
                                                                    ref={dragProvided.innerRef}
                                                                    {...dragProvided.draggableProps}
                                                                    {...dragProvided.dragHandleProps}
                                                                    className={`rounded-lg border border-gray-300 bg-white px-3 py-2.5 shadow-sm transition ${
                                                                        dragSnapshot.isDragging ? 'shadow-lg ring-1 ring-sky-300' : 'hover:bg-slate-50'
                                                                    }`}
                                                                >
                                                                    <div className="flex items-start justify-between gap-2">
                                                                        <p className="line-clamp-2 text-sm font-medium text-gray-900">
                                                                            {deal.title}
                                                                        </p>
                                                                        <GripVertical className="h-4 w-4 shrink-0 text-gray-400" />
                                                                    </div>

                                                                    <p className="mt-1.5 truncate text-xs text-gray-600">
                                                                        {deal.contact?.name ??
                                                                            deal.contact?.push_name ??
                                                                            deal.contact?.phone ??
                                                                            'Sin contacto'}
                                                                    </p>

                                                                    <div className="mt-2 flex items-center justify-between gap-2">
                                                                        <span className="rounded-md bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700">
                                                                            {deal.value
                                                                                ? `${deal.currency} ${Number(deal.value).toLocaleString('es-CO')}`
                                                                                : 'Sin valor'}
                                                                        </span>
                                                                        {movingDealId === deal.id && (
                                                                            <span className="text-[11px] text-emerald-700">Guardando...</span>
                                                                        )}
                                                                    </div>
                                                                </article>
                                                            )}
                                                        </Draggable>
                                                    ))}

                                                    {provided.placeholder}

                                                    {!board[stage.key].length && (
                                                        <div className="flex items-center gap-2 rounded-lg border border-dashed border-slate-300 bg-white/70 px-3 py-4 text-xs text-gray-500">
                                                            <BriefcaseBusiness className="h-4 w-4" />
                                                            <span>Sin deals</span>
                                                        </div>
                                                    )}
                                                </div>
                                            )}
                                        </Droppable>
                                    </section>
                                ))}
                            </div>
                        </div>
                    </DragDropContext>
                )}
                </div>
            </div>
        </AppLayout>
    );
}

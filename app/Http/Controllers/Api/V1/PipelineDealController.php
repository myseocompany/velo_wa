<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Pipeline\MoveDealToStage;
use App\Enums\DealStage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\PipelineDealRequest;
use App\Http\Requests\Api\V1\UpdateDealStageRequest;
use App\Http\Resources\PipelineDealResource;
use App\Models\PipelineDeal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class PipelineDealController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = PipelineDeal::query()->with(['contact', 'assignee']);

        $stage = $request->string('stage')->toString();
        if ($stage !== '' && DealStage::tryFrom($stage) !== null) {
            $query->where('stage', $stage);
        }

        $search = trim($request->string('search')->toString());
        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('title', 'ilike', '%' . $search . '%')
                    ->orWhere('notes', 'ilike', '%' . $search . '%');
            });
        }

        $assignedTo = $request->string('assigned_to')->toString();
        if ($assignedTo === 'me') {
            $query->where('assigned_to', $request->user()->id);
        } elseif ($assignedTo === 'unassigned') {
            $query->whereNull('assigned_to');
        } elseif ($assignedTo !== '') {
            $query->where('assigned_to', $assignedTo);
        }

        // Build ORDER BY CASE from enum definition — stays in sync automatically
        $cases    = collect(DealStage::cases())
            ->map(fn (DealStage $s, int $i) => "WHEN ? THEN {$i}")
            ->implode(' ');
        $bindings = array_column(DealStage::cases(), 'value');

        $perPage = max(1, min((int) $request->integer('per_page', 200), 500));

        $deals = $query
            ->orderByRaw("CASE stage {$cases} ELSE 999 END", $bindings)
            ->orderByDesc('updated_at')
            ->paginate($perPage);

        return PipelineDealResource::collection($deals);
    }

    public function store(PipelineDealRequest $request): JsonResponse
    {
        $stage = DealStage::tryFrom($request->input('stage', 'lead')) ?? DealStage::Lead;

        $deal = PipelineDeal::create([
            'tenant_id'       => $request->user()->tenant_id,
            'contact_id'      => $request->input('contact_id'),
            'conversation_id' => $request->input('conversation_id'),
            'title'           => $request->input('title'),
            'stage'           => $stage,
            'value'           => $request->input('value'),
            'currency'        => $request->input('currency', 'COP'),
            'assigned_to'     => $request->input('assigned_to'),
            'notes'           => $request->input('notes'),
            'lead_at'         => now(),
        ]);

        return response()->json(
            ['data' => new PipelineDealResource($deal->load(['contact', 'assignee']))],
            201,
        );
    }

    public function show(PipelineDeal $pipelineDeal): JsonResponse
    {
        return response()->json([
            'data' => new PipelineDealResource($pipelineDeal->load(['contact', 'assignee'])),
        ]);
    }

    public function update(PipelineDealRequest $request, PipelineDeal $pipelineDeal): JsonResponse
    {
        $pipelineDeal->update([
            'contact_id'      => $request->input('contact_id', $pipelineDeal->contact_id),
            'conversation_id' => $request->input('conversation_id', $pipelineDeal->conversation_id),
            'title'           => $request->input('title'),
            'value'           => $request->input('value'),
            'currency'        => $request->input('currency', $pipelineDeal->currency),
            'assigned_to'     => $request->input('assigned_to'),
            'notes'           => $request->input('notes'),
            'won_product'     => $request->input('won_product'),
            'lost_reason'     => $request->input('lost_reason'),
        ]);

        return response()->json([
            'data' => new PipelineDealResource($pipelineDeal->fresh(['contact', 'assignee'])),
        ]);
    }

    public function destroy(PipelineDeal $pipelineDeal): Response
    {
        $pipelineDeal->delete();

        return response()->noContent();
    }

    public function updateStage(
        UpdateDealStageRequest $request,
        PipelineDeal $pipelineDeal,
        MoveDealToStage $action,
    ): JsonResponse {
        $deal = $action->handle($pipelineDeal, $request->stage());

        return response()->json([
            'data' => new PipelineDealResource($deal->load(['contact', 'assignee'])),
        ]);
    }

    /**
     * Summary: count + value per stage for dashboard cards.
     */
    public function summary(Request $request): JsonResponse
    {
        $rows = PipelineDeal::query()
            ->selectRaw('stage, COUNT(*) as count, COALESCE(SUM(value), 0) as total_value')
            ->groupBy('stage')
            ->get()
            ->keyBy('stage');

        $summary = collect(DealStage::cases())->map(function (DealStage $stage) use ($rows) {
            $row = $rows->get($stage->value);
            return [
                'stage'       => $stage->value,
                'label'       => $stage->label(),
                'count'       => (int) ($row?->count ?? 0),
                'total_value' => (float) ($row?->total_value ?? 0),
            ];
        });

        $activePipeline = $summary
            ->whereNotIn('stage', ['closed_won', 'closed_lost'])
            ->sum('total_value');

        return response()->json([
            'by_stage'        => $summary->values(),
            'active_pipeline' => $activePipeline,
            'total_won'       => (float) ($rows->get('closed_won')?->total_value ?? 0),
            'total_lost'      => (float) ($rows->get('closed_lost')?->total_value ?? 0),
        ]);
    }
}

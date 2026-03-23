<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Pipeline\MoveDealToStage;
use App\Actions\Pipeline\ScheduleFollowUp;
use App\Enums\DealStage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\PipelineDealRequest;
use App\Http\Requests\Api\V1\ScheduleFollowUpRequest;
use App\Http\Requests\Api\V1\UpdateDealStageRequest;
use App\Http\Resources\PipelineDealResource;
use App\Models\PipelineDeal;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

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

        // Contact filter (used by inbox contact panel)
        $contactId = $request->string('contact_id')->toString();
        if ($contactId !== '') {
            $query->where('contact_id', $contactId);
        }

        // Value range filters
        $valueMin = $request->input('value_min');
        if ($valueMin !== null && is_numeric($valueMin)) {
            $query->where('value', '>=', (float) $valueMin);
        }
        $valueMax = $request->input('value_max');
        if ($valueMax !== null && is_numeric($valueMax)) {
            $query->where('value', '<=', (float) $valueMax);
        }

        // Date range filters (on created_at)
        $dateFrom = $request->string('date_from')->toString();
        if ($dateFrom !== '') {
            $query->whereDate('created_at', '>=', $dateFrom);
        }
        $dateTo = $request->string('date_to')->toString();
        if ($dateTo !== '') {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        // Follow-up filters
        $followUp = $request->string('follow_up')->toString();
        if ($followUp === 'overdue') {
            // Deals with a past follow_up_at that are still active
            $query->whereNotNull('follow_up_at')
                ->where('follow_up_at', '<=', now())
                ->whereNotIn('stage', [DealStage::ClosedWon->value, DealStage::ClosedLost->value]);
        } elseif ($followUp === 'upcoming') {
            // Deals with a future follow_up_at (next 7 days) still active
            $query->whereNotNull('follow_up_at')
                ->where('follow_up_at', '>', now())
                ->where('follow_up_at', '<=', now()->addDays(7))
                ->whereNotIn('stage', [DealStage::ClosedWon->value, DealStage::ClosedLost->value]);
        } elseif ($followUp === 'pending') {
            // Any deal with a scheduled follow-up
            $query->whereNotNull('follow_up_at')
                ->whereNotIn('stage', [DealStage::ClosedWon->value, DealStage::ClosedLost->value]);
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

    public function followUp(
        ScheduleFollowUpRequest $request,
        PipelineDeal $pipelineDeal,
        ScheduleFollowUp $action,
    ): JsonResponse {
        $followUpAt = $request->input('follow_up_at') !== null
            ? CarbonImmutable::parse($request->input('follow_up_at'))
            : null;

        $deal = $action->handle($pipelineDeal, $followUpAt, $request->input('follow_up_note'));

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

        // Average time spent in each stage (hours) across all tenant deals
        $tenantId = $request->user()->tenant_id;
        $dur = DB::selectOne("
            SELECT
                AVG(EXTRACT(EPOCH FROM (qualified_at   - lead_at))        / 3600) AS lead_hrs,
                AVG(EXTRACT(EPOCH FROM (proposal_at    - qualified_at))   / 3600) AS qualified_hrs,
                AVG(EXTRACT(EPOCH FROM (negotiation_at - proposal_at))    / 3600) AS proposal_hrs,
                AVG(EXTRACT(EPOCH FROM (closed_at      - negotiation_at)) / 3600) AS negotiation_hrs
            FROM pipeline_deals
            WHERE tenant_id = ? AND deleted_at IS NULL
        ", [$tenantId]);

        $stageDurations = [
            'lead'        => $dur && $dur->lead_hrs        !== null ? (float) round($dur->lead_hrs, 1)        : null,
            'qualified'   => $dur && $dur->qualified_hrs   !== null ? (float) round($dur->qualified_hrs, 1)   : null,
            'proposal'    => $dur && $dur->proposal_hrs    !== null ? (float) round($dur->proposal_hrs, 1)    : null,
            'negotiation' => $dur && $dur->negotiation_hrs !== null ? (float) round($dur->negotiation_hrs, 1) : null,
        ];

        $overdueCount = PipelineDeal::query()
            ->whereNotNull('follow_up_at')
            ->where('follow_up_at', '<=', now())
            ->whereNotIn('stage', [DealStage::ClosedWon->value, DealStage::ClosedLost->value])
            ->count();

        return response()->json([
            'by_stage'            => $summary->values(),
            'active_pipeline'     => $activePipeline,
            'total_won'           => (float) ($rows->get('closed_won')?->total_value ?? 0),
            'total_lost'          => (float) ($rows->get('closed_lost')?->total_value ?? 0),
            'stage_durations'     => $stageDurations,
            'overdue_follow_ups'  => $overdueCount,
        ]);
    }
}

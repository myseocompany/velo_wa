<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\DealStage;
use App\Http\Controllers\Controller;
use App\Http\Resources\PipelineDealResource;
use App\Models\PipelineDeal;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

class PipelineDealController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = PipelineDeal::query()->with(['contact', 'assignee']);

        $stage = $request->string('stage')->toString();
        if (in_array($stage, array_column(DealStage::cases(), 'value'), true)) {
            $query->where('stage', $stage);
        }

        $search = trim($request->string('search')->toString());
        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('title', 'like', '%' . $search . '%')
                    ->orWhere('notes', 'like', '%' . $search . '%');
            });
        }

        $perPage = max(1, min((int) $request->integer('per_page', 100), 200));

        $deals = $query
            ->orderByRaw("CASE stage
                WHEN 'lead' THEN 1
                WHEN 'qualified' THEN 2
                WHEN 'proposal' THEN 3
                WHEN 'negotiation' THEN 4
                WHEN 'closed_won' THEN 5
                WHEN 'closed_lost' THEN 6
                ELSE 7
            END")
            ->orderByDesc('updated_at')
            ->paginate($perPage);

        return PipelineDealResource::collection($deals);
    }

    public function updateStage(Request $request, PipelineDeal $pipelineDeal): JsonResponse
    {
        $validated = $request->validate([
            'stage' => ['required', Rule::in(array_column(DealStage::cases(), 'value'))],
        ]);

        /** @var DealStage $nextStage */
        $nextStage = DealStage::from($validated['stage']);
        $currentStage = $pipelineDeal->stage;

        if ($nextStage === $currentStage) {
            return response()->json([
                'data' => new PipelineDealResource($pipelineDeal->load(['contact', 'assignee'])),
            ]);
        }

        $now = CarbonImmutable::now();
        $updates = ['stage' => $nextStage];

        if ($pipelineDeal->lead_at === null && $nextStage === DealStage::Lead) {
            $updates['lead_at'] = $now;
        }
        if ($pipelineDeal->qualified_at === null && $nextStage === DealStage::Qualified) {
            $updates['qualified_at'] = $now;
        }
        if ($pipelineDeal->proposal_at === null && $nextStage === DealStage::Proposal) {
            $updates['proposal_at'] = $now;
        }
        if ($pipelineDeal->negotiation_at === null && $nextStage === DealStage::Negotiation) {
            $updates['negotiation_at'] = $now;
        }
        if ($pipelineDeal->closed_at === null && $nextStage->isClosed()) {
            $updates['closed_at'] = $now;
        }

        if (! $nextStage->isClosed()) {
            $updates['closed_at'] = null;
        }

        $pipelineDeal->fill($updates);
        $pipelineDeal->save();

        return response()->json([
            'data' => new PipelineDealResource($pipelineDeal->load(['contact', 'assignee'])),
        ]);
    }
}

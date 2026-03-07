<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Pipeline\MoveDealToStage;
use App\Enums\DealStage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateDealStageRequest;
use App\Http\Resources\PipelineDealResource;
use App\Models\PipelineDeal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

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
                $q->where('title', 'like', '%' . $search . '%')
                    ->orWhere('notes', 'like', '%' . $search . '%');
            });
        }

        // Build ORDER BY CASE from enum definition — stays in sync automatically
        $cases    = collect(DealStage::cases())
            ->map(fn (DealStage $s, int $i) => "WHEN ? THEN {$i}")
            ->implode(' ');
        $bindings = array_column(DealStage::cases(), 'value');

        $perPage = max(1, min((int) $request->integer('per_page', 100), 200));

        $deals = $query
            ->orderByRaw("CASE stage {$cases} ELSE 999 END", $bindings)
            ->orderByDesc('updated_at')
            ->paginate($perPage);

        return PipelineDealResource::collection($deals);
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
}

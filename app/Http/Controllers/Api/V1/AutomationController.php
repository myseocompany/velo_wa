<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\AutomationRequest;
use App\Http\Resources\AutomationResource;
use App\Models\Automation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class AutomationController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return AutomationResource::collection(
            Automation::query()->orderBy('priority')->orderBy('created_at')->get()
        );
    }

    public function store(AutomationRequest $request): JsonResponse
    {
        $automation = Automation::create([
            'tenant_id'      => $request->user()->tenant_id,
            'name'           => $request->input('name'),
            'trigger_type'   => $request->input('trigger_type'),
            'trigger_config' => $request->input('trigger_config', []),
            'action_type'    => $request->input('action_type'),
            'action_config'  => $request->input('action_config', []),
            'is_active'      => $request->boolean('is_active', true),
            'priority'       => $request->integer('priority', 100),
        ]);

        return response()->json(['data' => new AutomationResource($automation)], 201);
    }

    public function update(AutomationRequest $request, Automation $automation): JsonResponse
    {
        $automation->update([
            'name'           => $request->input('name'),
            'trigger_type'   => $request->input('trigger_type'),
            'trigger_config' => $request->input('trigger_config', []),
            'action_type'    => $request->input('action_type'),
            'action_config'  => $request->input('action_config', []),
            'is_active'      => $request->boolean('is_active', $automation->is_active),
            'priority'       => $request->integer('priority', $automation->priority),
        ]);

        return response()->json(['data' => new AutomationResource($automation->fresh())]);
    }

    public function destroy(Automation $automation): Response
    {
        $automation->delete();

        return response()->noContent();
    }

    public function toggle(Automation $automation): JsonResponse
    {
        $automation->update(['is_active' => ! $automation->is_active]);

        return response()->json(['data' => new AutomationResource($automation->fresh())]);
    }
}

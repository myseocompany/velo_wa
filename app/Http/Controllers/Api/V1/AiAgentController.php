<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\AiAgentRequest;
use App\Http\Resources\AiAgentResource;
use App\Models\AiAgent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiAgentController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $agent = AiAgent::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->first();

        if (! $agent) {
            $agent = new AiAgent([
                'tenant_id' => $tenantId,
                'name' => 'Agente IA',
                'system_prompt' => '',
                'llm_model' => AiAgentRequest::availableModels()[0],
                'is_enabled' => false,
                'context_messages' => 10,
            ]);
        }

        return response()->json([
            'data' => (new AiAgentResource($agent))->toArray($request),
            'available_models' => AiAgentRequest::availableModels(),
        ]);
    }

    public function upsert(AiAgentRequest $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $agent = AiAgent::withoutGlobalScope('tenant')->updateOrCreate(
            ['tenant_id' => $tenantId],
            [
                'name' => $request->input('name'),
                'system_prompt' => $request->input('system_prompt'),
                'llm_model' => $request->input('llm_model'),
                'context_messages' => $request->integer('context_messages'),
                'is_enabled' => $request->boolean('is_enabled', false),
            ]
        );

        return response()->json([
            'data' => new AiAgentResource($agent),
            'available_models' => AiAgentRequest::availableModels(),
        ]);
    }

    public function toggle(Request $request): JsonResponse
    {
        $request->validate([
            'enabled' => ['nullable', 'boolean'],
        ]);

        $tenantId = $request->user()->tenant_id;

        $agent = AiAgent::withoutGlobalScope('tenant')->firstOrCreate(
            ['tenant_id' => $tenantId],
            [
                'name' => 'Agente IA',
                'system_prompt' => '',
                'llm_model' => AiAgentRequest::availableModels()[0],
                'context_messages' => 10,
                'is_enabled' => false,
            ]
        );

        $enabled = $request->has('enabled')
            ? $request->boolean('enabled')
            : ! $agent->is_enabled;

        $agent->update(['is_enabled' => $enabled]);

        return response()->json([
            'data' => new AiAgentResource($agent->fresh()),
            'available_models' => AiAgentRequest::availableModels(),
        ]);
    }
}

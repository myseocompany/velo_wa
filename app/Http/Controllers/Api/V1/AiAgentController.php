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

        $agent = $this->defaultAgentQuery($tenantId)->first();

        if (! $agent) {
            $agent = new AiAgent([
                'tenant_id' => $tenantId,
                'name' => 'Agente IA',
                'system_prompt' => '',
                'llm_model' => AiAgentRequest::availableModels()[0],
                'is_enabled' => false,
                'is_default' => true,
                'context_messages' => 10,
            ]);
        }

        return response()->json([
            'data' => (new AiAgentResource($agent))->toArray($request),
            'available_models' => AiAgentRequest::availableModels(),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $agents = AiAgent::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->orderByDesc('is_default')
            ->orderBy('created_at')
            ->get();

        return response()->json([
            'data' => AiAgentResource::collection($agents),
            'default_agent_id' => $agents->firstWhere('is_default', true)?->id,
            'available_models' => AiAgentRequest::availableModels(),
        ]);
    }

    public function store(AiAgentRequest $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $hasAgents = AiAgent::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->exists();

        $agent = AiAgent::withoutGlobalScope('tenant')->create([
            'tenant_id' => $tenantId,
            'name' => $request->input('name'),
            'system_prompt' => $request->input('system_prompt'),
            'llm_model' => $request->input('llm_model'),
            'context_messages' => $request->integer('context_messages'),
            'is_enabled' => $request->boolean('is_enabled', false),
            'is_default' => ! $hasAgents,
        ]);

        return response()->json([
            'data' => new AiAgentResource($agent),
            'available_models' => AiAgentRequest::availableModels(),
        ], 201);
    }

    public function update(AiAgentRequest $request, string $aiAgent): JsonResponse
    {
        $aiAgent = $this->resolveAgent($request, $aiAgent);

        $aiAgent->update([
            'name' => $request->input('name'),
            'system_prompt' => $request->input('system_prompt'),
            'llm_model' => $request->input('llm_model'),
            'context_messages' => $request->integer('context_messages'),
            'is_enabled' => $request->boolean('is_enabled', false),
        ]);

        return response()->json([
            'data' => new AiAgentResource($aiAgent->fresh()),
            'available_models' => AiAgentRequest::availableModels(),
        ]);
    }

    public function destroy(Request $request, string $aiAgent): JsonResponse
    {
        $aiAgent = $this->resolveAgent($request, $aiAgent);

        $tenantId = $request->user()->tenant_id;
        $wasDefault = (bool) $aiAgent->is_default;

        $aiAgent->delete();

        if ($wasDefault) {
            $next = AiAgent::withoutGlobalScope('tenant')
                ->where('tenant_id', $tenantId)
                ->orderBy('created_at')
                ->first();

            if ($next) {
                $next->update(['is_default' => true]);
            }
        }

        return response()->json(['ok' => true]);
    }

    public function setDefault(Request $request, string $aiAgent): JsonResponse
    {
        $aiAgent = $this->resolveAgent($request, $aiAgent);

        $tenantId = $request->user()->tenant_id;

        AiAgent::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->update(['is_default' => false]);

        $aiAgent->update(['is_default' => true]);

        return response()->json([
            'data' => new AiAgentResource($aiAgent->fresh()),
            'available_models' => AiAgentRequest::availableModels(),
        ]);
    }

    public function toggleAgent(Request $request, string $aiAgent): JsonResponse
    {
        $request->validate([
            'enabled' => ['nullable', 'boolean'],
        ]);

        $aiAgent = $this->resolveAgent($request, $aiAgent);

        $enabled = $request->has('enabled')
            ? $request->boolean('enabled')
            : ! $aiAgent->is_enabled;

        $aiAgent->update(['is_enabled' => $enabled]);

        return response()->json([
            'data' => new AiAgentResource($aiAgent->fresh()),
            'available_models' => AiAgentRequest::availableModels(),
        ]);
    }

    // Backward compatibility endpoint: upsert default agent
    public function upsert(AiAgentRequest $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $agent = $this->defaultAgentQuery($tenantId)->first();

        if (! $agent) {
            $agent = AiAgent::withoutGlobalScope('tenant')->create([
                'tenant_id' => $tenantId,
                'name' => $request->input('name'),
                'system_prompt' => $request->input('system_prompt'),
                'llm_model' => $request->input('llm_model'),
                'context_messages' => $request->integer('context_messages'),
                'is_enabled' => $request->boolean('is_enabled', false),
                'is_default' => true,
            ]);
        } else {
            $agent->update([
                'name' => $request->input('name'),
                'system_prompt' => $request->input('system_prompt'),
                'llm_model' => $request->input('llm_model'),
                'context_messages' => $request->integer('context_messages'),
                'is_enabled' => $request->boolean('is_enabled', false),
            ]);
        }

        return response()->json([
            'data' => new AiAgentResource($agent->fresh()),
            'available_models' => AiAgentRequest::availableModels(),
        ]);
    }

    // Backward compatibility endpoint: toggle default agent
    public function toggle(Request $request): JsonResponse
    {
        $request->validate([
            'enabled' => ['nullable', 'boolean'],
        ]);

        $tenantId = $request->user()->tenant_id;

        $agent = $this->defaultAgentQuery($tenantId)->first();

        if (! $agent) {
            $agent = AiAgent::withoutGlobalScope('tenant')->create([
                'tenant_id' => $tenantId,
                'name' => 'Agente IA',
                'system_prompt' => '',
                'llm_model' => AiAgentRequest::availableModels()[0],
                'context_messages' => 10,
                'is_enabled' => false,
                'is_default' => true,
            ]);
        }

        $enabled = $request->has('enabled')
            ? $request->boolean('enabled')
            : ! $agent->is_enabled;

        $agent->update(['is_enabled' => $enabled]);

        return response()->json([
            'data' => new AiAgentResource($agent->fresh()),
            'available_models' => AiAgentRequest::availableModels(),
        ]);
    }

    private function defaultAgentQuery(string $tenantId)
    {
        return AiAgent::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->orderByDesc('is_default')
            ->orderBy('created_at');
    }

    private function resolveAgent(Request $request, string $aiAgentId): AiAgent
    {
        $agent = AiAgent::withoutGlobalScope('tenant')
            ->where('tenant_id', $request->user()->tenant_id)
            ->where('id', $aiAgentId)
            ->first();

        if (! $agent) {
            abort(404, 'AI agent not found.');
        }

        return $agent;
    }
}
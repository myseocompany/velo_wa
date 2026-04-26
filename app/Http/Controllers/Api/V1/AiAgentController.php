<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\AiProviderException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\AiAgentRequest;
use App\Http\Requests\Api\PlaygroundRequest;
use App\Http\Resources\AiAgentResource;
use App\Models\AiAgent;
use App\Services\AiAgentService;
use Illuminate\Database\QueryException;
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
                'tool_calling_enabled' => false,
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

        try {
            $agent = AiAgent::withoutGlobalScope('tenant')->create([
                'tenant_id' => $tenantId,
                'whatsapp_line_id' => $request->input('whatsapp_line_id'),
                'name' => $request->input('name'),
                'system_prompt' => $request->input('system_prompt'),
                'llm_model' => $request->input('llm_model'),
                'context_messages' => $request->integer('context_messages'),
                'is_enabled' => $request->boolean('is_enabled', false),
                'tool_calling_enabled' => $request->boolean('tool_calling_enabled', false),
                'is_default' => ! $hasAgents,
            ]);
        } catch (QueryException $exception) {
            return $this->agentConstraintError($exception);
        }

        return response()->json([
            'data' => new AiAgentResource($agent),
            'available_models' => AiAgentRequest::availableModels(),
        ], 201);
    }

    public function update(AiAgentRequest $request, string $aiAgent): JsonResponse
    {
        $aiAgent = $this->resolveAgent($request, $aiAgent);

        try {
            $aiAgent->update([
                'whatsapp_line_id' => $request->input('whatsapp_line_id'),
                'name' => $request->input('name'),
                'system_prompt' => $request->input('system_prompt'),
                'llm_model' => $request->input('llm_model'),
                'context_messages' => $request->integer('context_messages'),
                'is_enabled' => $request->boolean('is_enabled', false),
                'tool_calling_enabled' => $request->boolean('tool_calling_enabled', false),
            ]);
        } catch (QueryException $exception) {
            return $this->agentConstraintError($exception);
        }

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

    public function playground(PlaygroundRequest $request, string $aiAgent, AiAgentService $service): JsonResponse
    {
        $aiAgent = $this->resolveAgent($request, $aiAgent);

        $prompt = trim((string) $aiAgent->system_prompt);
        if ($prompt === '') {
            return response()->json([
                'message' => 'El agente no tiene system_prompt configurado.',
            ], 422);
        }

        /** @var array<int, array{role: string, content: string}> $history */
        $history = $request->input('history', []);
        $history[] = [
            'role' => 'user',
            'content' => $request->string('message')->toString(),
        ];

        try {
            $reply = $service->generateReplyWithMeta(
                $aiAgent,
                $prompt,
                $history,
                disableFallback: true,
                context: ['playground' => true],
            );
        } catch (AiProviderException $exception) {
            return response()->json([
                'message' => $this->playgroundProviderErrorMessage($exception),
                'provider' => $exception->provider,
                'status' => $exception->status,
            ], 502);
        }

        if ($reply === null) {
            return response()->json([
                'message' => 'El proveedor no devolvió una respuesta.',
            ], 502);
        }

        return response()->json([
            'data' => $reply,
        ]);
    }

    private function playgroundProviderErrorMessage(AiProviderException $exception): string
    {
        if ($exception->status !== 0) {
            return $exception->getMessage();
        }

        if (str_contains(strtolower($exception->getMessage()), 'key')) {
            return 'La API key del proveedor '.$exception->provider.' no está configurada.';
        }

        return 'No se pudo conectar con '.$exception->provider.'. Reintenta en unos segundos.';
    }

    // Backward compatibility endpoint: upsert default agent
    public function upsert(AiAgentRequest $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $agent = $this->defaultAgentQuery($tenantId)->first();

        if (! $agent) {
            try {
                $agent = AiAgent::withoutGlobalScope('tenant')->create([
                    'tenant_id' => $tenantId,
                    'name' => $request->input('name'),
                    'whatsapp_line_id' => $request->input('whatsapp_line_id'),
                    'system_prompt' => $request->input('system_prompt'),
                    'llm_model' => $request->input('llm_model'),
                    'context_messages' => $request->integer('context_messages'),
                    'is_enabled' => $request->boolean('is_enabled', false),
                    'tool_calling_enabled' => $request->boolean('tool_calling_enabled', false),
                    'is_default' => true,
                ]);
            } catch (QueryException $exception) {
                return $this->agentConstraintError($exception);
            }
        } else {
            try {
                $agent->update([
                    'whatsapp_line_id' => $request->input('whatsapp_line_id'),
                    'name' => $request->input('name'),
                    'system_prompt' => $request->input('system_prompt'),
                    'llm_model' => $request->input('llm_model'),
                    'context_messages' => $request->integer('context_messages'),
                    'is_enabled' => $request->boolean('is_enabled', false),
                    'tool_calling_enabled' => $request->boolean('tool_calling_enabled', false),
                ]);
            } catch (QueryException $exception) {
                return $this->agentConstraintError($exception);
            }
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
                'tool_calling_enabled' => false,
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

    private function agentConstraintError(QueryException $exception): JsonResponse
    {
        if (str_contains($exception->getMessage(), 'ai_agents_unique_per_line')) {
            return response()->json([
                'message' => 'Ya existe un agente configurado para esa línea de WhatsApp.',
                'errors' => ['whatsapp_line_id' => ['Ya existe un agente configurado para esa línea de WhatsApp.']],
            ], 422);
        }

        throw $exception;
    }
}

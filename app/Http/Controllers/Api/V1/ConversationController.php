<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Conversations\AssignConversation;
use App\Actions\Conversations\CloseConversation;
use App\Actions\Conversations\CreateConversation;
use App\Actions\Conversations\ReopenConversation;
use App\Enums\ConversationStatus;
use App\Events\ConversationUpdated;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\AssignConversationRequest;
use App\Http\Requests\Api\StoreConversationRequest;
use App\Http\Resources\ConversationResource;
use App\Models\AiAgent;
use App\Models\AutomationLog;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Order;
use App\Models\PipelineDeal;
use App\Models\Reservation;
use App\Models\Task;
use App\Models\User;
use App\Models\WhatsAppLine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class ConversationController extends Controller
{
    public function store(StoreConversationRequest $request, CreateConversation $action): JsonResponse
    {
        $tenant = $request->user()->tenant;
        $requestedLineId = $request->input('whatsapp_line_id');
        $line = null;

        if ($requestedLineId) {
            $line = WhatsAppLine::query()
                ->where('tenant_id', $tenant->id)
                ->where('id', $requestedLineId)
                ->first();

            abort_unless($line, 422, 'The selected WhatsApp line does not exist.');
        } else {
            $line = $tenant->defaultWhatsAppLine()->first();

            abort_unless($line, 422, 'No WhatsApp lines are configured for this tenant.');
        }

        abort_unless($line->isConnected(), 422, 'Line is not connected.');

        $result = $action->handle($request->user(), $request->validated(), (string) $line->id);
        $conversation = $result['conversation'];

        broadcast(new ConversationUpdated($conversation));

        return response()->json([
            'data' => (new ConversationResource($conversation))->toArray($request),
        ], $result['created'] ? 201 : 200);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Conversation::query()->with([
            'contact',
            'assignee',
            'whatsappLine',
            'messages' => fn ($q) => $q->reorder()->orderByDesc('created_at')->limit(1),
        ]);

        $status = $request->string('status')->toString();
        if (in_array($status, array_column(ConversationStatus::cases(), 'value'), true)) {
            $query->where('status', $status);
        }

        $assigned = $request->string('assigned')->toString();
        if ($assigned === 'me') {
            $query->where('assigned_to', $request->user()->id);
        } elseif ($assigned === 'unassigned') {
            $query->whereNull('assigned_to');
        } elseif ($assigned !== '' && $assigned !== 'all') {
            // Restrict to users within the same tenant to prevent cross-tenant enumeration
            $query->where('assigned_to', $assigned)
                ->whereHas('assignee', fn ($q) => $q->where('tenant_id', $request->user()->tenant_id));
        }

        $lineId = $request->string('whatsapp_line_id')->toString();
        if ($lineId !== '') {
            $query->where('whatsapp_line_id', $lineId);
        }

        $search = trim($request->string('search')->toString());
        if ($search !== '') {
            // Normalize phone search: strip +, spaces, dashes so "+57 301 5639627" matches "573015639627"
            $phoneSearch = preg_replace('/[\s\+\-\(\)]+/', '', $search);

            $query->whereHas('contact', function ($contactQuery) use ($search, $phoneSearch): void {
                $contactQuery->where(function ($q) use ($search, $phoneSearch): void {
                    $q->where('name', 'ilike', '%'.$search.'%')
                        ->orWhere('push_name', 'ilike', '%'.$search.'%');

                    if ($phoneSearch !== '') {
                        $q->orWhere('phone', 'ilike', '%'.$phoneSearch.'%');
                    }
                });
            });
        }

        $limit = (int) $request->integer('limit', 25);
        $limit = max(1, min($limit, 100));

        $conversations = $query
            ->orderByDesc('last_message_at')
            ->orderByDesc('created_at')
            ->cursorPaginate($limit);

        return ConversationResource::collection($conversations);
    }

    public function show(Conversation $conversation): ConversationResource
    {
        $conversation->load([
            'contact',
            'assignee',
            'whatsappLine',
            'messages' => fn ($q) => $q->reorder()->orderByDesc('created_at')->limit(1),
        ]);

        return new ConversationResource($conversation);
    }

    public function assign(AssignConversationRequest $request, Conversation $conversation, AssignConversation $action): ConversationResource
    {
        $agent = $request->filled('assigned_to')
            ? User::find($request->input('assigned_to'))
            : null;

        $conversation = $action->handle($conversation, $agent);

        return new ConversationResource($conversation->fresh(['contact', 'assignee', 'whatsappLine', 'messages']));
    }

    public function close(Request $request, Conversation $conversation, CloseConversation $action): ConversationResource
    {
        if ($conversation->status === ConversationStatus::Closed) {
            abort(422, 'La conversación ya está cerrada.');
        }

        $conversation = $action->handle($conversation, $request->user());

        return new ConversationResource($conversation->fresh(['contact', 'assignee', 'whatsappLine', 'messages']));
    }

    public function reopen(Conversation $conversation, ReopenConversation $action): ConversationResource
    {
        if ($conversation->status !== ConversationStatus::Closed) {
            abort(422, 'Solo se pueden reabrir conversaciones cerradas.');
        }

        $conversation = $action->handle($conversation);

        return new ConversationResource($conversation->fresh(['contact', 'assignee', 'whatsappLine', 'messages']));
    }

    public function destroy(Request $request, Conversation $conversation): Response
    {
        if (! in_array($request->user()->role, ['owner', 'admin'], true)) {
            abort(403, 'No autorizado para eliminar conversaciones.');
        }

        DB::transaction(function () use ($conversation): void {
            $tenantId = (string) $conversation->tenant_id;
            $conversationId = (string) $conversation->id;

            Message::withoutGlobalScope('tenant')
                ->where('tenant_id', $tenantId)
                ->where('conversation_id', $conversationId)
                ->delete();

            AutomationLog::withoutGlobalScope('tenant')
                ->where('tenant_id', $tenantId)
                ->where('conversation_id', $conversationId)
                ->delete();

            PipelineDeal::withoutGlobalScope('tenant')
                ->where('tenant_id', $tenantId)
                ->where('conversation_id', $conversationId)
                ->update(['conversation_id' => null]);

            Order::withoutGlobalScope('tenant')
                ->where('tenant_id', $tenantId)
                ->where('conversation_id', $conversationId)
                ->update(['conversation_id' => null]);

            Reservation::withoutGlobalScope('tenant')
                ->where('tenant_id', $tenantId)
                ->where('conversation_id', $conversationId)
                ->update(['conversation_id' => null]);

            Task::withoutGlobalScope('tenant')
                ->where('tenant_id', $tenantId)
                ->where('conversation_id', $conversationId)
                ->update(['conversation_id' => null]);

            $conversation->delete();
        });

        return response()->noContent();
    }

    public function toggleAiAgent(Request $request, Conversation $conversation): ConversationResource
    {
        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
        ]);

        $agent = AiAgent::withoutGlobalScope('tenant')
            ->where('tenant_id', $request->user()->tenant_id)
            ->first();

        $globalEnabled = (bool) ($agent?->is_enabled ?? false);
        $enabled = (bool) $validated['enabled'];

        $conversation->update([
            'ai_agent_enabled' => $enabled === $globalEnabled ? null : $enabled,
        ]);

        return new ConversationResource($conversation->fresh(['contact', 'assignee', 'messages']));
    }
}

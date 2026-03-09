<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Conversations\AssignConversation;
use App\Actions\Conversations\CloseConversation;
use App\Actions\Conversations\ReopenConversation;
use App\Enums\ConversationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\AssignConversationRequest;
use App\Http\Resources\ConversationResource;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ConversationController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Conversation::query()->with([
            'contact',
            'assignee',
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

        $search = trim($request->string('search')->toString());
        if ($search !== '') {
            // Normalize phone search: strip +, spaces, dashes so "+57 301 5639627" matches "573015639627"
            $phoneSearch = preg_replace('/[\s\+\-\(\)]+/', '', $search);

            $query->whereHas('contact', function ($contactQuery) use ($search, $phoneSearch): void {
                $contactQuery->where(function ($q) use ($search, $phoneSearch): void {
                    $q->where('name', 'ilike', '%' . $search . '%')
                        ->orWhere('push_name', 'ilike', '%' . $search . '%')
                        ->orWhere('phone', 'ilike', '%' . $phoneSearch . '%');
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

        return new ConversationResource($conversation->fresh(['contact', 'assignee', 'messages']));
    }

    public function close(Request $request, Conversation $conversation, CloseConversation $action): ConversationResource
    {
        if ($conversation->status === ConversationStatus::Closed) {
            abort(422, 'La conversación ya está cerrada.');
        }

        $conversation = $action->handle($conversation, $request->user());

        return new ConversationResource($conversation->fresh(['contact', 'assignee', 'messages']));
    }

    public function reopen(Conversation $conversation, ReopenConversation $action): ConversationResource
    {
        if ($conversation->status !== ConversationStatus::Closed) {
            abort(422, 'Solo se pueden reabrir conversaciones cerradas.');
        }

        $conversation = $action->handle($conversation);

        return new ConversationResource($conversation->fresh(['contact', 'assignee', 'messages']));
    }
}

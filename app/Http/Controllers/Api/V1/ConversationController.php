<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\ConversationStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\ConversationResource;
use App\Models\Conversation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ConversationController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Conversation::query()->with(['contact', 'assignee', 'latestMessage']);

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
            $query->where('assigned_to', $assigned);
        }

        $search = trim($request->string('search')->toString());
        if ($search !== '') {
            $query->whereHas('contact', function ($contactQuery) use ($search): void {
                $contactQuery->where(function ($q) use ($search): void {
                    $q->where('name', 'like', '%' . $search . '%')
                        ->orWhere('push_name', 'like', '%' . $search . '%')
                        ->orWhere('phone', 'like', '%' . $search . '%');
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
        $conversation->load(['contact', 'assignee', 'latestMessage']);

        return new ConversationResource($conversation);
    }
}

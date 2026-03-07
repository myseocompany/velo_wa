<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ConversationResource;
use App\Models\Conversation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ConversationController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $conversations = Conversation::with(['contact', 'assignee'])
            ->orderByDesc('last_message_at')
            ->paginate(30);

        return ConversationResource::collection($conversations);
    }

    public function show(Conversation $conversation): ConversationResource
    {
        $conversation->load(['contact', 'assignee']);

        return new ConversationResource($conversation);
    }
}

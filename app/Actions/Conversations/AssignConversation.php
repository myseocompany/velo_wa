<?php

declare(strict_types=1);

namespace App\Actions\Conversations;

use App\Events\ConversationUpdated;
use App\Models\Conversation;
use App\Models\User;

class AssignConversation
{
    public function handle(Conversation $conversation, ?User $agent): Conversation
    {
        $conversation->update([
            'assigned_to' => $agent?->id,
            'assigned_at' => $agent ? now() : null,
        ]);

        broadcast(new ConversationUpdated($conversation->fresh(['contact', 'assignee', 'messages'])));

        return $conversation;
    }
}

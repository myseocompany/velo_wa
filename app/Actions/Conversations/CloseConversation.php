<?php

declare(strict_types=1);

namespace App\Actions\Conversations;

use App\Enums\ConversationStatus;
use App\Events\ConversationUpdated;
use App\Models\Conversation;
use App\Models\User;

class CloseConversation
{
    public function handle(Conversation $conversation, User $closedBy): Conversation
    {
        $conversation->update([
            'status'    => ConversationStatus::Closed,
            'closed_at' => now(),
            'closed_by' => $closedBy->id,
        ]);

        broadcast(new ConversationUpdated($conversation->fresh(['contact', 'assignee', 'messages'])));

        return $conversation;
    }
}

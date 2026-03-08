<?php

declare(strict_types=1);

namespace App\Actions\Conversations;

use App\Enums\ConversationStatus;
use App\Events\ConversationUpdated;
use App\Models\Conversation;

class ReopenConversation
{
    public function handle(Conversation $conversation): Conversation
    {
        $conversation->update([
            'status'         => ConversationStatus::Open,
            'closed_at'      => null,
            'closed_by'      => null,
            'reopen_count'   => $conversation->reopen_count + 1,
        ]);

        broadcast(new ConversationUpdated($conversation->fresh(['contact', 'assignee', 'messages'])));

        return $conversation;
    }
}

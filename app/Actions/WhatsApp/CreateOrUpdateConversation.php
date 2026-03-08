<?php

declare(strict_types=1);

namespace App\Actions\WhatsApp;

use App\Enums\Channel;
use App\Enums\ConversationStatus;
use App\Models\Contact;
use App\Models\Conversation;

class CreateOrUpdateConversation
{
    public function handle(Contact $contact, bool &$isNew = false): Conversation
    {
        // Find an existing open or pending conversation for this contact
        /** @var Conversation|null $conversation */
        $conversation = Conversation::withoutGlobalScope('tenant')
            ->where('tenant_id', $contact->tenant_id)
            ->where('contact_id', $contact->id)
            ->whereIn('status', [ConversationStatus::Open->value, ConversationStatus::Pending->value])
            ->latest('last_message_at')
            ->first();

        if ($conversation) {
            $isNew = false;
            return $conversation;
        }

        $isNew = true;

        return Conversation::create([
            'tenant_id'        => $contact->tenant_id,
            'contact_id'       => $contact->id,
            'status'           => ConversationStatus::Open,
            'channel'          => Channel::WhatsApp,
            'first_message_at' => now(),
            'last_message_at'  => now(),
        ]);
    }
}

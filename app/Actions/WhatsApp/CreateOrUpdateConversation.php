<?php

declare(strict_types=1);

namespace App\Actions\WhatsApp;

use App\Enums\Channel;
use App\Enums\ConversationStatus;
use App\Models\Contact;
use App\Models\Conversation;
use Illuminate\Support\Facades\DB;

class CreateOrUpdateConversation
{
    public function handle(Contact $contact, bool &$isNew = false, ?string $whatsappLineId = null): Conversation
    {
        return DB::transaction(function () use ($contact, &$isNew, $whatsappLineId) {
            // Lock the contact row to serialize concurrent webhook processing
            // for the same contact across queue workers, preventing duplicate conversations.
            Contact::withoutGlobalScope('tenant')
                ->where('id', $contact->id)
                ->lockForUpdate()
                ->first();

            /** @var Conversation|null $conversation */
            $conversation = Conversation::withoutGlobalScope('tenant')
                ->where('tenant_id', $contact->tenant_id)
                ->where('contact_id', $contact->id)
                ->when($whatsappLineId, fn ($query) => $query->where('whatsapp_line_id', $whatsappLineId))
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
                'whatsapp_line_id' => $whatsappLineId,
                'status'           => ConversationStatus::Open,
                'channel'          => Channel::WhatsApp,
                'first_message_at' => now(),
                'last_message_at'  => now(),
            ]);
        });
    }
}

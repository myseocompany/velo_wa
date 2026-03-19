<?php

declare(strict_types=1);

namespace App\Actions\Conversations;

use App\Enums\Channel;
use App\Enums\ContactSource;
use App\Enums\ConversationStatus;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateConversation
{
    /**
     * @param  array{
     *     phone: string,
     *     name?: ?string,
     *     email?: ?string,
     *     company?: ?string,
     *     notes?: ?string,
     *     assigned_to?: ?string
     * }  $data
     * @return array{conversation: Conversation, created: bool}
     */
    public function handle(User $user, array $data): array
    {
        return DB::transaction(function () use ($user, $data): array {
            $contact = $this->resolveContact($user, $data);

            $conversation = Conversation::withoutGlobalScope('tenant')
                ->where('tenant_id', $user->tenant_id)
                ->where('contact_id', $contact->id)
                ->whereIn('status', [ConversationStatus::Open->value, ConversationStatus::Pending->value])
                ->lockForUpdate()
                ->orderByDesc('last_message_at')
                ->orderByDesc('created_at')
                ->first();

            if ($conversation) {
                return [
                    'conversation' => $conversation->load(['contact', 'assignee', 'messages']),
                    'created' => false,
                ];
            }

            $assignedTo = $data['assigned_to'] ?? null;
            $now = now();

            $conversation = Conversation::create([
                'tenant_id' => $user->tenant_id,
                'contact_id' => $contact->id,
                'status' => ConversationStatus::Open,
                'channel' => Channel::WhatsApp,
                'assigned_to' => $assignedTo,
                'assigned_at' => $assignedTo ? $now : null,
            ]);

            $contactUpdates = ['last_contact_at' => $now];
            if (! $contact->first_contact_at) {
                $contactUpdates['first_contact_at'] = $now;
            }
            $contact->update($contactUpdates);

            return [
                'conversation' => $conversation->fresh(['contact', 'assignee', 'messages']),
                'created' => true,
            ];
        });
    }

    /**
     * @param  array{
     *     phone: string,
     *     name?: ?string,
     *     email?: ?string,
     *     company?: ?string,
     *     notes?: ?string,
     *     assigned_to?: ?string
     * }  $data
     */
    private function resolveContact(User $user, array $data): Contact
    {
        $contact = Contact::withoutGlobalScope('tenant')
            ->where('tenant_id', $user->tenant_id)
            ->where('phone', $data['phone'])
            ->lockForUpdate()
            ->first();

        if (! $contact) {
            return Contact::create([
                'tenant_id' => $user->tenant_id,
                'phone' => $data['phone'],
                'name' => $data['name'] ?? null,
                'email' => $data['email'] ?? null,
                'company' => $data['company'] ?? null,
                'notes' => $data['notes'] ?? null,
                'assigned_to' => $data['assigned_to'] ?? null,
                'source' => ContactSource::Manual,
            ]);
        }

        $updates = [];

        if (! $contact->name && ! empty($data['name'])) {
            $updates['name'] = $data['name'];
        }

        if (! $contact->email && ! empty($data['email'])) {
            $updates['email'] = $data['email'];
        }

        if (! $contact->company && ! empty($data['company'])) {
            $updates['company'] = $data['company'];
        }

        if (! $contact->notes && ! empty($data['notes'])) {
            $updates['notes'] = $data['notes'];
        }

        if (! $contact->assigned_to && ! empty($data['assigned_to'])) {
            $updates['assigned_to'] = $data['assigned_to'];
        }

        if ($updates !== []) {
            $contact->update($updates);
        }

        return $contact;
    }
}

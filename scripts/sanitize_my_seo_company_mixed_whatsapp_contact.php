<?php

declare(strict_types=1);

use App\Enums\Channel;
use App\Enums\ContactSource;
use App\Enums\ConversationStatus;
use App\Models\Contact;
use App\Models\ContactIdentityAlias;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

$tenantId = '019d0099-a43a-703d-b1d0-264d667dc148';
$sourceContactId = '019d2fc2-dbc7-73a6-bba9-a8dca76c0066';
$sourceConversationId = '019d2fc2-dbea-7203-807f-78b2746ab695';

$targets = [
    'nestor' => [
        'contact_id' => $sourceContactId,
        'conversation_id' => $sourceConversationId,
        'name' => 'Nestor',
        'push_name' => 'Nestor',
        'phone' => '573012881060',
        'wa_id' => '573012881060@s.whatsapp.net',
        'alias' => '573012881060@s.whatsapp.net',
        'message_ids' => [],
    ],
    'eduardo' => [
        'name' => 'Eduardo',
        'push_name' => 'Eduardo',
        'phone' => '573112065857',
        'wa_id' => '573112065857@s.whatsapp.net',
        'alias' => '573112065857@s.whatsapp.net',
        'message_ids' => [
            '3EB066F9CA807B31E7D1AB',
            '3EB030F9CC444717618719',
            'ACF6DA3B0BF97913783222BC2F58B70D',
            '3EB0E3739D7703C56C4CD6',
            'AC44C2A2C7E397A6E3B5A0C24D0BAF4D',
            'AC731211677E0CCE4AA520ABD6E3C102',
            '3EB0767AEF2F12AC3ECE8A',
        ],
    ],
    'angel' => [
        'name' => 'Angel',
        'push_name' => 'Angel',
        'phone' => '573186344554',
        'wa_id' => '573186344554@s.whatsapp.net',
        'alias' => '573186344554@s.whatsapp.net',
        'message_ids' => [
            '3EB0FCEB200893FC9B3610',
            'A5A0D38A2EAC944FFBEC8DBA8E13665C',
            '3EB07AEB893A6424F914BC',
        ],
    ],
    'wilfredo' => [
        'name' => 'Wilfredo',
        'push_name' => 'Wilfredo',
        'phone' => '573133892681',
        'wa_id' => '573133892681@s.whatsapp.net',
        'alias' => '573133892681@s.whatsapp.net',
        'message_ids' => [
            '3EB06F438BC89E0309EDAD',
            'ACAEC24E7EFAD7ABE9F9E09762CDD163',
            'ACA306A391CBA76BA552EB16C3862D98',
            '3EB0D95639F47EDC58BCDF',
            'ACD9E192C36E05834743A993C111EADF',
            '3EB0350B52127C4F19AFB2',
            'AC68FFFD7BFF865C67020BBFEDC00F7E',
            '3EB0AC4BAFE45183FC10C0',
        ],
    ],
];

$backupDir = storage_path('app/sanitization-backups');
if (! is_dir($backupDir)) {
    mkdir($backupDir, 0775, true);
}

$backupPath = $backupDir.'/my-seo-company-mixed-contact-'.now()->format('Ymd_His').'.json';

$backup = [
    'generated_at' => now()->toIso8601String(),
    'tenant_id' => $tenantId,
    'source_contact_id' => $sourceContactId,
    'source_conversation_id' => $sourceConversationId,
    'contacts' => DB::table('contacts')
        ->where('tenant_id', $tenantId)
        ->where(function ($query) use ($sourceContactId, $targets): void {
            $query->where('id', $sourceContactId)
                ->orWhereIn('wa_id', array_values(array_filter(array_map(
                    static fn (array $target) => $target['wa_id'] ?? null,
                    $targets,
                ))));
        })
        ->orderBy('created_at')
        ->get(),
    'aliases' => DB::table('contact_identity_aliases')
        ->where('tenant_id', $tenantId)
        ->where(function ($query) use ($sourceContactId, $targets): void {
            $query->where('contact_id', $sourceContactId)
                ->orWhereIn('alias', array_values(array_filter(array_map(
                    static fn (array $target) => $target['alias'] ?? null,
                    $targets,
                ))));
        })
        ->orderBy('created_at')
        ->get(),
    'conversations' => DB::table('conversations')
        ->where('tenant_id', $tenantId)
        ->where(function ($query) use ($sourceConversationId): void {
            $query->where('id', $sourceConversationId);
        })
        ->orderBy('created_at')
        ->get(),
    'messages' => DB::table('messages')
        ->where('tenant_id', $tenantId)
        ->where('conversation_id', $sourceConversationId)
        ->orderBy('created_at')
        ->get(),
];

file_put_contents($backupPath, json_encode($backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

$sourceMessages = Message::withoutGlobalScope('tenant')
    ->where('tenant_id', $tenantId)
    ->where('conversation_id', $sourceConversationId)
    ->orderBy('created_at')
    ->get();

$allTargetMessageIds = collect($targets)
    ->except('nestor')
    ->flatMap(static fn (array $target) => $target['message_ids'])
    ->values();

$missingMessageIds = $allTargetMessageIds
    ->reject(fn (string $waMessageId) => $sourceMessages->contains('wa_message_id', $waMessageId))
    ->values();

if ($missingMessageIds->isNotEmpty()) {
    throw new RuntimeException('Missing message ids in source conversation: '.$missingMessageIds->implode(', '));
}

DB::transaction(function () use ($tenantId, $sourceContactId, $sourceConversationId, $targets): void {
    foreach ($targets as $key => &$target) {
        $contact = null;

        if (isset($target['contact_id'])) {
            $contact = Contact::withoutGlobalScope('tenant')
                ->where('tenant_id', $tenantId)
                ->where('id', $target['contact_id'])
                ->lockForUpdate()
                ->first();
        }

        if (! $contact) {
            $contact = Contact::withoutGlobalScope('tenant')
                ->where('tenant_id', $tenantId)
                ->where('wa_id', $target['wa_id'])
                ->lockForUpdate()
                ->first();
        }

        if (! $contact) {
            $contact = ContactIdentityAlias::withoutGlobalScope('tenant')
                ->where('tenant_id', $tenantId)
                ->where('alias', $target['alias'])
                ->lockForUpdate()
                ->first()?->contact;
        }

        if (! $contact) {
            $contact = Contact::withoutGlobalScopes()->create([
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'wa_id' => $target['wa_id'],
                'phone' => $target['phone'],
                'name' => $target['name'],
                'push_name' => $target['push_name'],
                'source' => ContactSource::WhatsApp,
                'first_contact_at' => now(),
                'last_contact_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $contact->forceFill([
                'wa_id' => $target['wa_id'],
                'phone' => $target['phone'],
                'name' => $target['name'],
                'push_name' => $target['push_name'],
                'source' => $contact->source ?? ContactSource::WhatsApp,
            ])->save();
        }

        $target['contact_id'] = (string) $contact->id;

        $alias = ContactIdentityAlias::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->where('alias', $target['alias'])
            ->lockForUpdate()
            ->first();

        if ($alias) {
            $alias->forceFill([
                'contact_id' => $contact->id,
                'alias_type' => 'pn',
                'last_seen_at' => $alias->last_seen_at ?? now(),
            ])->save();
        } else {
            ContactIdentityAlias::withoutGlobalScopes()->create([
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'contact_id' => $contact->id,
                'alias' => $target['alias'],
                'alias_type' => 'pn',
                'first_seen_at' => now(),
                'last_seen_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if ($key === 'nestor') {
            $target['conversation_id'] = $sourceConversationId;
            continue;
        }

        $conversation = Conversation::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->where('contact_id', $contact->id)
            ->whereIn('status', [ConversationStatus::Open->value, ConversationStatus::Pending->value])
            ->lockForUpdate()
            ->first();

        if (! $conversation) {
            $conversation = Conversation::withoutGlobalScopes()->create([
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'contact_id' => $contact->id,
                'status' => ConversationStatus::Open,
                'channel' => Channel::WhatsApp,
                'assigned_to' => null,
                'assigned_at' => null,
                'ai_agent_enabled' => null,
                'first_message_at' => now(),
                'first_response_at' => null,
                'last_message_at' => now(),
                'message_count' => 0,
                'closed_at' => null,
                'closed_by' => null,
                'reopen_count' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $target['conversation_id'] = (string) $conversation->id;

        Message::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->whereIn('wa_message_id', $target['message_ids'])
            ->update([
                'conversation_id' => $conversation->id,
                'updated_at' => now(),
            ]);
    }
    unset($target);

    ContactIdentityAlias::withoutGlobalScope('tenant')
        ->where('tenant_id', $tenantId)
        ->where('contact_id', $sourceContactId)
        ->where('alias', '!=', $targets['nestor']['alias'])
        ->delete();

    foreach ($targets as $target) {
        recalculateConversation($target['conversation_id'], $target['contact_id'], $target['name'], $target['push_name']);
    }
});

echo "Backup written to {$backupPath}".PHP_EOL;
echo "Sanitization completed.".PHP_EOL;

function recalculateConversation(string $conversationId, string $contactId, string $name, string $pushName): void
{
    $conversation = Conversation::withoutGlobalScope('tenant')->findOrFail($conversationId);
    $contact = Contact::withoutGlobalScope('tenant')->findOrFail($contactId);

    $messages = Message::withoutGlobalScope('tenant')
        ->where('tenant_id', $conversation->tenant_id)
        ->where('conversation_id', $conversation->id)
        ->orderBy('created_at')
        ->get(['direction', 'created_at']);

    if ($messages->isEmpty()) {
        return;
    }

    $firstMessage = $messages->first();
    $lastMessage = $messages->last();
    $firstInbound = $messages->firstWhere('direction', 'in');
    $firstResponseAt = null;

    if ($firstInbound) {
        $firstOutboundAfterInbound = $messages->first(
            static fn ($message) => $message->direction === 'out' && $message->created_at >= $firstInbound->created_at
        );
        $firstResponseAt = $firstOutboundAfterInbound?->created_at;
    }

    $conversation->forceFill([
        'contact_id' => $contact->id,
        'first_message_at' => $firstMessage->created_at,
        'last_message_at' => $lastMessage->created_at,
        'first_response_at' => $firstResponseAt,
        'message_count' => $messages->count(),
        'updated_at' => now(),
    ])->save();

    $contact->forceFill([
        'name' => $name,
        'push_name' => $pushName,
        'first_contact_at' => $contact->first_contact_at
            ? ($contact->first_contact_at->lessThan($firstMessage->created_at) ? $contact->first_contact_at : $firstMessage->created_at)
            : $firstMessage->created_at,
        'last_contact_at' => $contact->last_contact_at
            ? ($contact->last_contact_at->greaterThan($lastMessage->created_at) ? $contact->last_contact_at : $lastMessage->created_at)
            : $lastMessage->created_at,
        'updated_at' => now(),
    ])->save();
}

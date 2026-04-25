<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\Channel;
use App\Enums\ConversationStatus;
use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Events\MessageReceived;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Tenant;
use App\Models\WhatsAppLine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MessageReceivedTest extends TestCase
{
    use RefreshDatabase;

    public function test_broadcast_includes_whatsapp_line_id(): void
    {
        $tenant = Tenant::create([
            'name' => 'Tenant',
            'slug' => 'tenant',
        ]);

        $line = WhatsAppLine::create([
            'tenant_id' => $tenant->id,
            'label' => 'Linea 2',
            'instance_id' => 'line_2',
            'is_default' => false,
            'status' => 'connected',
        ]);

        $contact = Contact::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'phone' => '573004410097',
            'wa_id' => '573004410097@s.whatsapp.net',
        ]);

        $conversation = Conversation::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'contact_id' => $contact->id,
            'whatsapp_line_id' => $line->id,
            'status' => ConversationStatus::Open,
            'channel' => Channel::WhatsApp,
        ]);

        $message = Message::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'conversation_id' => $conversation->id,
            'direction' => MessageDirection::In,
            'body' => 'hola',
            'status' => MessageStatus::Delivered,
        ]);

        $payload = (new MessageReceived($message))->broadcastWith();

        $this->assertSame($conversation->id, $payload['conversation_id']);
        $this->assertSame($line->id, $payload['whatsapp_line_id']);
    }
}

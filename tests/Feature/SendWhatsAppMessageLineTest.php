<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Channel;
use App\Enums\ConversationStatus;
use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Enums\WaStatus;
use App\Jobs\SendWhatsAppMessage;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Tenant;
use App\Models\WhatsAppLine;
use App\Services\WhatsAppClientService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class SendWhatsAppMessageLineTest extends TestCase
{
    use RefreshDatabase;

    public function test_message_is_marked_failed_when_line_is_disconnected(): void
    {
        $tenant = Tenant::create(['name' => 'T', 'slug' => 't']);

        $line = WhatsAppLine::create([
            'tenant_id' => $tenant->id,
            'label' => 'Principal',
            'instance_id' => 'line_offline',
            'is_default' => true,
            'status' => WaStatus::Disconnected,
        ]);

        $contact = Contact::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'phone' => '573000000000',
            'wa_id' => '573000000000',
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
            'direction' => MessageDirection::Out,
            'body' => 'Hola',
            'status' => MessageStatus::Pending,
        ]);

        $client = Mockery::mock(WhatsAppClientService::class);
        $client->shouldNotReceive('sendText');
        $client->shouldNotReceive('sendMedia');

        (new SendWhatsAppMessage($message))->handle($client);

        $message->refresh();
        $this->assertSame(MessageStatus::Failed->value, $message->status->value);
        $this->assertSame('Line disconnected', $message->error_message);
    }
}

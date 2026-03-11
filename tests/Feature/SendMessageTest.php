<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Channel;
use App\Enums\ConversationStatus;
use App\Enums\MessageStatus;
use App\Jobs\SendWhatsAppMessage;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SendMessageTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $agent;
    private Contact $contact;
    private Conversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant',
            'wa_instance_id' => 'test_instance',
        ]);

        $this->agent = User::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->contact = Contact::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'phone'     => '573015639627',
            'name'      => 'Test Contact',
            'wa_id'     => '573015639627',
        ]);

        $this->conversation = Conversation::withoutGlobalScopes()->create([
            'tenant_id'  => $this->tenant->id,
            'contact_id' => $this->contact->id,
            'status'     => ConversationStatus::Open,
            'channel'    => Channel::WhatsApp,
        ]);
    }

    public function test_agent_can_send_text_message(): void
    {
        Queue::fake();

        $response = $this->actingAs($this->agent)->postJson(
            "/api/v1/conversations/{$this->conversation->id}/messages",
            ['body' => 'Hola, ¿en qué te puedo ayudar?']
        );

        $response->assertCreated();
        $response->assertJsonPath('data.status', MessageStatus::Pending->value);
        $response->assertJsonPath('data.body', 'Hola, ¿en qué te puedo ayudar?');

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $this->conversation->id,
            'tenant_id'       => $this->tenant->id,
            'body'            => 'Hola, ¿en qué te puedo ayudar?',
            'status'          => MessageStatus::Pending->value,
        ]);

        Queue::assertPushed(SendWhatsAppMessage::class);
    }

    public function test_send_message_requires_body(): void
    {
        $response = $this->actingAs($this->agent)->postJson(
            "/api/v1/conversations/{$this->conversation->id}/messages",
            []
        );

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['body']);
    }

    public function test_send_message_increments_conversation_counter(): void
    {
        Queue::fake();

        $this->actingAs($this->agent)->postJson(
            "/api/v1/conversations/{$this->conversation->id}/messages",
            ['body' => 'Primer mensaje']
        );

        $this->assertEquals(1, $this->conversation->fresh()->message_count);
    }

    public function test_message_list_returns_newest_first(): void
    {
        $older = Message::withoutGlobalScopes()->create([
            'tenant_id'       => $this->tenant->id,
            'conversation_id' => $this->conversation->id,
            'direction'       => 'out',
            'body'            => 'Mensaje viejo',
            'status'          => MessageStatus::Sent,
            'created_at'      => now()->subMinutes(5),
        ]);

        $newer = Message::withoutGlobalScopes()->create([
            'tenant_id'       => $this->tenant->id,
            'conversation_id' => $this->conversation->id,
            'direction'       => 'out',
            'body'            => 'Mensaje nuevo',
            'status'          => MessageStatus::Sent,
            'created_at'      => now(),
        ]);

        $response = $this->actingAs($this->agent)->getJson(
            "/api/v1/conversations/{$this->conversation->id}/messages"
        );

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->toArray();
        $this->assertEquals([$newer->id, $older->id], $ids);
    }

    public function test_unauthenticated_user_cannot_send_messages(): void
    {
        $response = $this->postJson(
            "/api/v1/conversations/{$this->conversation->id}/messages",
            ['body' => 'Mensaje sin auth']
        );

        $response->assertUnauthorized();
    }

    public function test_send_message_fails_gracefully_when_contact_soft_deleted(): void
    {
        Queue::fake();

        // Soft-delete the contact BEFORE sending
        $this->contact->delete();

        // The API still creates the message (contact check happens in the job)
        $response = $this->actingAs($this->agent)->postJson(
            "/api/v1/conversations/{$this->conversation->id}/messages",
            ['body' => 'Mensaje a contacto eliminado']
        );

        $response->assertCreated();

        // Job is dispatched but marks itself as failed upon execution
        $message = Message::withoutGlobalScopes()->latest()->first();
        Queue::assertPushed(SendWhatsAppMessage::class, fn ($job) => true);

        // Simulate job execution — should mark message as failed, not throw
        $job = new SendWhatsAppMessage($message);
        $this->assertDoesNotThrow(fn () => app()->call([$job, 'handle'], [
            \App\Services\WhatsAppClientService::class => $this->createMock(\App\Services\WhatsAppClientService::class),
        ]));

        $this->assertEquals(
            MessageStatus::Failed->value,
            $message->fresh()->status->value
        );
    }
}

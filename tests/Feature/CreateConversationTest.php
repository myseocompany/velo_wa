<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Channel;
use App\Enums\ContactSource;
use App\Enums\ConversationStatus;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateConversationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $agent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant',
        ]);

        $this->agent = User::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    public function test_agent_can_create_contact_and_conversation_from_inbox(): void
    {
        $response = $this->actingAs($this->agent)->postJson('/api/v1/conversations', [
            'phone' => '+57 301 563 9627',
            'name' => 'Laura Gómez',
            'email' => 'laura@example.com',
            'company' => 'Velo',
            'assigned_to' => $this->agent->id,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.status', ConversationStatus::Open->value);
        $response->assertJsonPath('data.channel', Channel::WhatsApp->value);
        $response->assertJsonPath('data.contact.phone', '573015639627');
        $response->assertJsonPath('data.contact.name', 'Laura Gómez');
        $response->assertJsonPath('data.contact.source', ContactSource::Manual->value);
        $response->assertJsonPath('data.assigned_to', $this->agent->id);

        $this->assertDatabaseHas('contacts', [
            'tenant_id' => $this->tenant->id,
            'phone' => '573015639627',
            'name' => 'Laura Gómez',
            'source' => ContactSource::Manual->value,
        ]);

        $this->assertDatabaseHas('conversations', [
            'tenant_id' => $this->tenant->id,
            'assigned_to' => $this->agent->id,
            'status' => ConversationStatus::Open->value,
            'channel' => Channel::WhatsApp->value,
            'message_count' => 0,
        ]);
    }

    public function test_existing_active_conversation_is_reused_for_same_phone(): void
    {
        $contact = Contact::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'phone' => '573015639627',
            'name' => 'Cliente existente',
            'source' => ContactSource::Manual,
        ]);

        $conversation = Conversation::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'contact_id' => $contact->id,
            'status' => ConversationStatus::Open,
            'channel' => Channel::WhatsApp,
        ]);

        $response = $this->actingAs($this->agent)->postJson('/api/v1/conversations', [
            'phone' => '573015639627',
            'name' => 'Nombre nuevo',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.id', $conversation->id);

        $this->assertSame(1, Contact::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->count());
        $this->assertSame(1, Conversation::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->count());
        $this->assertSame('Cliente existente', $contact->fresh()->name);
    }

    public function test_closed_conversation_allows_creating_a_new_active_one(): void
    {
        $contact = Contact::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'phone' => '573015639627',
            'source' => ContactSource::Manual,
        ]);

        Conversation::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'contact_id' => $contact->id,
            'status' => ConversationStatus::Closed,
            'channel' => Channel::WhatsApp,
            'closed_at' => now()->subDay(),
        ]);

        $response = $this->actingAs($this->agent)->postJson('/api/v1/conversations', [
            'phone' => '573015639627',
        ]);

        $response->assertCreated();

        $this->assertSame(
            2,
            Conversation::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->count()
        );

        $this->assertSame(
            1,
            Conversation::withoutGlobalScopes()
                ->where('tenant_id', $this->tenant->id)
                ->where('status', ConversationStatus::Open->value)
                ->count()
        );
    }

    public function test_unauthenticated_user_cannot_create_conversation(): void
    {
        $response = $this->postJson('/api/v1/conversations', [
            'phone' => '573015639627',
        ]);

        $response->assertUnauthorized();
    }
}

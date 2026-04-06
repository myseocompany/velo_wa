<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Channel;
use App\Enums\ContactSource;
use App\Enums\ConversationStatus;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\LoyaltyAccount;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderApiTest extends TestCase
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
            'name' => 'Tenant Orders',
            'slug' => 'tenant-orders',
        ]);

        $this->agent = User::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->contact = Contact::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'phone' => '573001112233',
            'name' => 'Cliente Pedidos',
            'source' => ContactSource::Manual,
        ]);

        $this->conversation = Conversation::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'contact_id' => $this->contact->id,
            'status' => ConversationStatus::Open,
            'channel' => Channel::WhatsApp,
        ]);
    }

    public function test_agent_can_create_order(): void
    {
        $response = $this->actingAs($this->agent)->postJson('/api/v1/orders', [
            'contact_id' => $this->contact->id,
            'conversation_id' => $this->conversation->id,
            'total' => 48900,
            'currency' => 'COP',
            'notes' => 'Sin cebolla',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.status', 'new');
        $response->assertJsonPath('data.contact_id', $this->contact->id);
        $response->assertJsonPath('data.conversation_id', $this->conversation->id);
        $response->assertJsonMissingPath('data.tenant_id');

        $this->assertDatabaseHas('orders', [
            'tenant_id' => $this->tenant->id,
            'contact_id' => $this->contact->id,
            'status' => 'new',
            'currency' => 'COP',
            'notes' => 'Sin cebolla',
        ]);
    }

    public function test_agent_can_move_order_status_to_delivered(): void
    {
        $orderResponse = $this->actingAs($this->agent)->postJson('/api/v1/orders', [
            'contact_id' => $this->contact->id,
            'conversation_id' => $this->conversation->id,
            'total' => 25500,
        ]);

        $orderId = $orderResponse->json('data.id');

        $response = $this->actingAs($this->agent)->patchJson("/api/v1/orders/{$orderId}/status", [
            'status' => 'delivered',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.status', 'delivered');
        $this->assertNotNull($response->json('data.delivered_at'));
    }

    public function test_delivered_timestamp_is_preserved_on_subsequent_status_change(): void
    {
        $orderResponse = $this->actingAs($this->agent)->postJson('/api/v1/orders', [
            'contact_id' => $this->contact->id,
        ]);
        $orderId = $orderResponse->json('data.id');

        $this->actingAs($this->agent)->patchJson("/api/v1/orders/{$orderId}/status", ['status' => 'delivered']);

        $response = $this->actingAs($this->agent)->patchJson("/api/v1/orders/{$orderId}/status", ['status' => 'confirmed']);

        $response->assertOk();
        $response->assertJsonPath('data.status', 'confirmed');
        $this->assertNotNull($response->json('data.delivered_at'), 'delivered_at must be preserved after status change');
    }

    public function test_loyalty_points_awarded_on_delivery(): void
    {
        $orderResponse = $this->actingAs($this->agent)->postJson('/api/v1/orders', [
            'contact_id' => $this->contact->id,
            'total' => 50000,
        ]);
        $orderId = $orderResponse->json('data.id');

        $this->actingAs($this->agent)->patchJson("/api/v1/orders/{$orderId}/status", ['status' => 'delivered']);

        $this->assertDatabaseHas('loyalty_accounts', [
            'tenant_id' => $this->tenant->id,
            'contact_id' => $this->contact->id,
        ]);

        $account = LoyaltyAccount::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)
            ->where('contact_id', $this->contact->id)
            ->first();

        $this->assertNotNull($account);
        $this->assertGreaterThan(0, $account->points_balance);
        $this->assertDatabaseHas('loyalty_events', [
            'tenant_id' => $this->tenant->id,
            'contact_id' => $this->contact->id,
            'type' => 'order_reward',
        ]);
    }

    public function test_loyalty_points_not_awarded_twice_for_same_order(): void
    {
        $orderResponse = $this->actingAs($this->agent)->postJson('/api/v1/orders', [
            'contact_id' => $this->contact->id,
            'total' => 50000,
        ]);
        $orderId = $orderResponse->json('data.id');

        $this->actingAs($this->agent)->patchJson("/api/v1/orders/{$orderId}/status", ['status' => 'delivered']);
        $this->actingAs($this->agent)->patchJson("/api/v1/orders/{$orderId}/status", ['status' => 'confirmed']);
        $this->actingAs($this->agent)->patchJson("/api/v1/orders/{$orderId}/status", ['status' => 'delivered']);

        $this->assertDatabaseCount('loyalty_events', 1);
    }

    public function test_agent_cannot_access_other_tenant_orders(): void
    {
        $otherTenant = Tenant::create(['name' => 'Otro Tenant', 'slug' => 'otro-tenant']);
        $otherContact = Contact::withoutGlobalScopes()->create([
            'tenant_id' => $otherTenant->id,
            'phone' => '573009998877',
            'name' => 'Contacto Otro',
            'source' => ContactSource::Manual,
        ]);
        $otherOrder = \App\Models\Order::withoutGlobalScopes()->create([
            'tenant_id' => $otherTenant->id,
            'contact_id' => $otherContact->id,
            'code' => 'PED-OTHER',
            'status' => 'new',
            'currency' => 'COP',
            'new_at' => now(),
        ]);

        $response = $this->actingAs($this->agent)->getJson("/api/v1/orders/{$otherOrder->id}");

        $response->assertNotFound();
    }

    public function test_agent_cannot_use_other_tenant_contact_in_order(): void
    {
        $otherTenant = Tenant::create(['name' => 'Otro Tenant 2', 'slug' => 'otro-tenant-2']);
        $otherContact = Contact::withoutGlobalScopes()->create([
            'tenant_id' => $otherTenant->id,
            'phone' => '573001234567',
            'name' => 'Contacto Externo',
            'source' => ContactSource::Manual,
        ]);

        $response = $this->actingAs($this->agent)->postJson('/api/v1/orders', [
            'contact_id' => $otherContact->id,
        ]);

        $response->assertUnprocessable();
    }

    public function test_order_update_does_not_require_all_fields(): void
    {
        $orderResponse = $this->actingAs($this->agent)->postJson('/api/v1/orders', [
            'contact_id' => $this->contact->id,
            'total' => 10000,
            'notes' => 'Nota original',
        ]);
        $orderId = $orderResponse->json('data.id');

        $response = $this->actingAs($this->agent)->putJson("/api/v1/orders/{$orderId}", [
            'notes' => 'Nota actualizada',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.notes', 'Nota actualizada');
        $response->assertJsonPath('data.contact_id', $this->contact->id);
    }

    public function test_invalid_status_rejected(): void
    {
        $orderResponse = $this->actingAs($this->agent)->postJson('/api/v1/orders', [
            'contact_id' => $this->contact->id,
        ]);
        $orderId = $orderResponse->json('data.id');

        $response = $this->actingAs($this->agent)->patchJson("/api/v1/orders/{$orderId}/status", [
            'status' => 'invalid_status',
        ]);

        $response->assertUnprocessable();
    }
}

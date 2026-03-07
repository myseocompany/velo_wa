<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebhookSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.evolution.key' => 'test-webhook-key']);
    }

    public function test_webhook_rejects_requests_without_valid_api_key(): void
    {
        $response = $this->postJson('/api/v1/webhooks/evolution', [
            'event' => 'connection.update',
            'instance' => 'tenant_test',
            'data' => ['state' => 'open'],
        ]);

        $response
            ->assertStatus(401)
            ->assertJson(['message' => 'Unauthorized webhook request.']);
    }

    public function test_webhook_accepts_requests_with_valid_api_key_and_logs_tenant(): void
    {
        $tenant = Tenant::create([
            'name' => 'Webhook Tenant',
            'slug' => 'webhook-tenant',
            'wa_instance_id' => 'tenant_test',
        ]);

        $response = $this
            ->withHeader('apikey', 'test-webhook-key')
            ->postJson('/api/v1/webhooks/evolution', [
                'event' => 'connection.update',
                'instance' => 'tenant_test',
                'data' => ['state' => 'close'],
            ]);

        $response->assertOk()->assertJson(['ok' => true]);

        $this->assertDatabaseHas('webhook_logs', [
            'tenant_id' => $tenant->id,
            'event_type' => 'connection.update',
            'source' => 'whatsapp',
        ]);
    }
}

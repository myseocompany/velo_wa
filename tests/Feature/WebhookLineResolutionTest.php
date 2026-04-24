<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\WaStatus;
use App\Jobs\HandleInboundMessage;
use App\Models\Tenant;
use App\Models\WhatsAppLine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WebhookLineResolutionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.evolution.key' => 'test-webhook-key']);
    }

    public function test_webhook_dispatches_inbound_job_with_line_id(): void
    {
        Queue::fake();

        $tenant = Tenant::create(['name' => 'T', 'slug' => 't']);
        $line = WhatsAppLine::create([
            'tenant_id' => $tenant->id,
            'label' => 'Ventas',
            'instance_id' => 'line_abc123',
            'is_default' => true,
            'status' => WaStatus::Connected,
        ]);

        $response = $this
            ->withHeader('apikey', 'test-webhook-key')
            ->postJson('/api/v1/webhooks/evolution', [
                'event' => 'messages.upsert',
                'instance' => 'line_abc123',
                'data' => [
                    'key' => ['id' => 'wa-msg-1', 'remoteJid' => '573000000000@s.whatsapp.net', 'fromMe' => false],
                    'message' => ['conversation' => 'hola'],
                    'messageTimestamp' => now()->timestamp,
                ],
            ]);

        $response->assertOk();

        Queue::assertPushed(HandleInboundMessage::class, function (HandleInboundMessage $job) use ($tenant, $line): bool {
            // The job holds tenantId + whatsappLineId as constructor args. Serialize and inspect via reflection.
            $ref = new \ReflectionClass($job);
            $tenantIdProp = $ref->getProperty('tenantId');
            $lineIdProp = $ref->getProperty('whatsappLineId');

            return $tenantIdProp->getValue($job) === $tenant->id
                && $lineIdProp->getValue($job) === $line->id;
        });
    }

    public function test_webhook_with_legacy_tenant_instance_id_backfills_default_line(): void
    {
        $tenant = Tenant::create([
            'name' => 'Legacy',
            'slug' => 'legacy',
            'wa_instance_id' => 'tenant_legacy1',
        ]);

        $this->assertFalse($tenant->whatsappLines()->exists());

        $response = $this
            ->withHeader('apikey', 'test-webhook-key')
            ->postJson('/api/v1/webhooks/evolution', [
                'event' => 'connection.update',
                'instance' => 'tenant_legacy1',
                'data' => ['state' => 'close'],
            ]);

        $response->assertOk();

        $this->assertDatabaseHas('whatsapp_lines', [
            'tenant_id' => $tenant->id,
            'is_default' => true,
        ]);
    }

    public function test_webhook_with_unknown_instance_is_ignored(): void
    {
        $response = $this
            ->withHeader('apikey', 'test-webhook-key')
            ->postJson('/api/v1/webhooks/evolution', [
                'event' => 'connection.update',
                'instance' => 'ghost_instance',
                'data' => ['state' => 'close'],
            ]);

        $response->assertOk();
        $response->assertJson(['ignored' => true]);
    }
}

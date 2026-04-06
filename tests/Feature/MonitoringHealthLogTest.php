<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\CheckInstanceHealth;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WaHealthLog;
use App\Services\WhatsAppClientService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class MonitoringHealthLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_whatsapp_health_logs_endpoint_is_tenant_scoped(): void
    {
        $tenantA = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a']);
        $tenantB = Tenant::create(['name' => 'Tenant B', 'slug' => 'tenant-b']);

        $userA = User::factory()->create(['tenant_id' => $tenantA->id]);

        WaHealthLog::create([
            'tenant_id' => $tenantA->id,
            'instance_name' => 'tenant_a',
            'state' => 'open',
            'is_healthy' => true,
            'response_ms' => 123,
            'checked_at' => now(),
        ]);

        WaHealthLog::create([
            'tenant_id' => $tenantB->id,
            'instance_name' => 'tenant_b',
            'state' => 'close',
            'is_healthy' => false,
            'response_ms' => 456,
            'checked_at' => now(),
        ]);

        $response = $this->actingAs($userA)->getJson('/api/v1/whatsapp/health-logs?limit=20');
        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.instance_name', 'tenant_a');
    }

    public function test_health_check_triggers_alert_threshold_and_recovery(): void
    {
        $tenant = Tenant::create([
            'name' => 'Tenant Alert',
            'slug' => 'tenant-alert',
            'wa_instance_id' => 'tenant_alert',
        ]);

        $job = new CheckInstanceHealth();

        $failingClient = \Mockery::mock(WhatsAppClientService::class);
        $failingClient->shouldReceive('getConnectionState')->times(3)->andThrow(new RuntimeException('Evolution down'));

        $job->handle($failingClient);
        $job->handle($failingClient);
        $job->handle($failingClient);

        $tenant->refresh();
        $this->assertSame(3, $tenant->wa_health_consecutive_failures);
        $this->assertNotNull($tenant->wa_health_last_alert_at);

        $this->assertDatabaseHas('activity_log', [
            'description' => 'wa_health_unhealthy_threshold_reached',
            'subject_id' => $tenant->id,
        ]);

        $healthyClient = \Mockery::mock(WhatsAppClientService::class);
        $healthyClient->shouldReceive('getConnectionState')->once()->andReturn('open');

        $job->handle($healthyClient);

        $tenant->refresh();
        $this->assertSame(0, $tenant->wa_health_consecutive_failures);

        $this->assertDatabaseHas('activity_log', [
            'description' => 'wa_health_recovered',
            'subject_id' => $tenant->id,
        ]);
    }
}

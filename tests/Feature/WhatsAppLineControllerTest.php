<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Channel;
use App\Enums\ConversationStatus;
use App\Enums\WaStatus;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WhatsAppLine;
use App\Services\WhatsAppClientService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class WhatsAppLineControllerTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        // Cashier's Billable trait adds a subscriptions hasMany that defaults to a
        // `tenant_id` FK — our subscriptions table uses Cashier's polymorphic schema,
        // so live queries fail in tests. Preload an empty relation on every Tenant read
        // to short-circuit subscription lookups during `currentPlan()`.
        Tenant::retrieved(function (Tenant $tenant): void {
            if (! $tenant->relationLoaded('subscriptions')) {
                $tenant->setRelation('subscriptions', collect());
            }
        });

        $this->tenant = Tenant::create([
            'name' => 'Multi-line Tenant',
            'slug' => 'multi-line',
        ]);

        $this->owner = User::factory()->owner()->create(['tenant_id' => $this->tenant->id]);

        $this->app->instance(
            WhatsAppClientService::class,
            Mockery::mock(WhatsAppClientService::class)->shouldIgnoreMissing()
        );
    }

    public function test_index_returns_only_tenant_lines(): void
    {
        WhatsAppLine::create([
            'tenant_id' => $this->tenant->id,
            'label' => 'Ventas',
            'is_default' => true,
        ]);

        $otherTenant = Tenant::create(['name' => 'Other', 'slug' => 'other']);
        WhatsAppLine::create([
            'tenant_id' => $otherTenant->id,
            'label' => 'Foreign',
            'is_default' => true,
        ]);

        $response = $this->actingAs($this->owner)->getJson('/api/v1/whatsapp/lines');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.label', 'Ventas');
    }

    public function test_store_creates_first_line_as_default(): void
    {
        $response = $this->actingAs($this->owner)->postJson('/api/v1/whatsapp/lines', [
            'label' => 'Principal',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.label', 'Principal');
        $response->assertJsonPath('data.is_default', true);

        $this->assertDatabaseHas('whatsapp_lines', [
            'tenant_id' => $this->tenant->id,
            'label' => 'Principal',
            'is_default' => true,
        ]);
    }

    public function test_store_respects_plan_limit(): void
    {
        // Trial plan allows 1 line
        WhatsAppLine::create([
            'tenant_id' => $this->tenant->id,
            'label' => 'Existing',
            'is_default' => true,
        ]);

        $response = $this->actingAs($this->owner)->postJson('/api/v1/whatsapp/lines', [
            'label' => 'Second',
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('whatsapp_lines', 1);
    }

    public function test_update_promotes_line_to_default(): void
    {
        $first = WhatsAppLine::create([
            'tenant_id' => $this->tenant->id,
            'label' => 'First',
            'is_default' => true,
        ]);

        $second = WhatsAppLine::create([
            'tenant_id' => $this->tenant->id,
            'label' => 'Second',
            'is_default' => false,
        ]);

        $response = $this->actingAs($this->owner)
            ->patchJson("/api/v1/whatsapp/lines/{$second->id}", ['is_default' => true]);

        $response->assertOk();

        $this->assertFalse($first->fresh()->is_default);
        $this->assertTrue($second->fresh()->is_default);
    }

    public function test_destroy_blocks_when_line_has_open_conversations(): void
    {
        $line = WhatsAppLine::create([
            'tenant_id' => $this->tenant->id,
            'label' => 'Busy',
            'is_default' => true,
        ]);

        $contact = Contact::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'phone' => '573000000000',
            'wa_id' => '573000000000',
        ]);

        Conversation::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'contact_id' => $contact->id,
            'whatsapp_line_id' => $line->id,
            'status' => ConversationStatus::Open,
            'channel' => Channel::WhatsApp,
        ]);

        $response = $this->actingAs($this->owner)
            ->deleteJson("/api/v1/whatsapp/lines/{$line->id}");

        $response->assertStatus(422);
        $this->assertNull($line->fresh()->deleted_at);
    }

    public function test_destroy_blocks_default_when_other_lines_exist(): void
    {
        $default = WhatsAppLine::create([
            'tenant_id' => $this->tenant->id,
            'label' => 'Default',
            'is_default' => true,
        ]);

        WhatsAppLine::create([
            'tenant_id' => $this->tenant->id,
            'label' => 'Secondary',
            'is_default' => false,
        ]);

        $response = $this->actingAs($this->owner)
            ->deleteJson("/api/v1/whatsapp/lines/{$default->id}");

        $response->assertStatus(422);
        $this->assertNull($default->fresh()->deleted_at);
    }

    public function test_destroy_soft_deletes_idle_line(): void
    {
        $line = WhatsAppLine::create([
            'tenant_id' => $this->tenant->id,
            'label' => 'Idle',
            'is_default' => true,
            'status' => WaStatus::Disconnected,
        ]);

        $response = $this->actingAs($this->owner)
            ->deleteJson("/api/v1/whatsapp/lines/{$line->id}");

        $response->assertOk();
        $this->assertSoftDeleted('whatsapp_lines', ['id' => $line->id]);
    }

    public function test_cross_tenant_line_access_returns_404(): void
    {
        $otherTenant = Tenant::create(['name' => 'Other', 'slug' => 'other']);
        $foreignLine = WhatsAppLine::create([
            'tenant_id' => $otherTenant->id,
            'label' => 'Foreign',
            'is_default' => true,
        ]);

        // BelongsToTenant global scope applies to route model binding,
        // so the foreign line is not resolvable by the current tenant.
        $response = $this->actingAs($this->owner)
            ->patchJson("/api/v1/whatsapp/lines/{$foreignLine->id}", ['label' => 'Hijack']);

        $response->assertNotFound();
    }
}

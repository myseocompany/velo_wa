<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ContactSource;
use App\Models\Contact;
use App\Models\LoyaltyAccount;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoyaltyApiTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $admin;

    private User $agent;

    private Contact $contact;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'Tenant Loyalty', 'slug' => 'tenant-loyalty']);

        $this->admin = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'admin',
        ]);

        $this->agent = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'agent',
        ]);

        $this->contact = Contact::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'phone' => '573001234567',
            'name' => 'Cliente Loyalty',
            'source' => ContactSource::Manual,
        ]);
    }

    public function test_agent_can_view_loyalty_account(): void
    {
        $response = $this->actingAs($this->agent)
            ->getJson("/api/v1/loyalty/contacts/{$this->contact->id}/account");

        $response->assertOk();
        $response->assertJsonStructure(['data' => ['id', 'contact_id', 'points_balance', 'total_earned', 'total_redeemed']]);
        $response->assertJsonPath('data.points_balance', 0);
    }

    public function test_admin_can_adjust_loyalty_points(): void
    {
        $response = $this->actingAs($this->admin)->postJson(
            "/api/v1/loyalty/contacts/{$this->contact->id}/adjust",
            ['points' => 100, 'description' => 'Bono bienvenida']
        );

        $response->assertOk();
        $response->assertJsonPath('data.points_balance', 100);
        $response->assertJsonPath('data.total_earned', 100);

        $this->assertDatabaseHas('loyalty_events', [
            'tenant_id' => $this->tenant->id,
            'contact_id' => $this->contact->id,
            'type' => 'manual_adjustment',
            'points' => 100,
        ]);
    }

    public function test_agent_cannot_adjust_loyalty_points(): void
    {
        $response = $this->actingAs($this->agent)->postJson(
            "/api/v1/loyalty/contacts/{$this->contact->id}/adjust",
            ['points' => 100]
        );

        $response->assertForbidden();
    }

    public function test_cannot_redeem_more_points_than_balance(): void
    {
        $this->actingAs($this->admin)->postJson(
            "/api/v1/loyalty/contacts/{$this->contact->id}/adjust",
            ['points' => 50]
        );

        $response = $this->actingAs($this->admin)->postJson(
            "/api/v1/loyalty/contacts/{$this->contact->id}/adjust",
            ['points' => -100, 'description' => 'Canje excesivo']
        );

        $response->assertUnprocessable();

        $account = LoyaltyAccount::withoutGlobalScopes()
            ->where('contact_id', $this->contact->id)
            ->first();
        $this->assertEquals(50, $account->points_balance);
    }

    public function test_loyalty_events_scoped_to_tenant(): void
    {
        $otherTenant = Tenant::create(['name' => 'Otro Tenant', 'slug' => 'otro-loyalty']);
        $otherContact = Contact::withoutGlobalScopes()->create([
            'tenant_id' => $otherTenant->id,
            'phone' => '573009990000',
            'name' => 'Otro Cliente',
            'source' => ContactSource::Manual,
        ]);

        LoyaltyAccount::withoutGlobalScopes()->create([
            'tenant_id' => $otherTenant->id,
            'contact_id' => $otherContact->id,
            'points_balance' => 500,
            'total_earned' => 500,
            'total_redeemed' => 0,
        ]);

        $response = $this->actingAs($this->agent)
            ->getJson("/api/v1/loyalty/contacts/{$otherContact->id}/account");

        $response->assertNotFound();
    }
}

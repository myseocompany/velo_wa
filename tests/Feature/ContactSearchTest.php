<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactSearchTest extends TestCase
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

    private function createContact(array $attrs = []): Contact
    {
        return Contact::withoutGlobalScopes()->create(array_merge([
            'tenant_id' => $this->tenant->id,
            'phone'     => '573015639627',
            'name'      => 'Juan Pérez',
            'wa_id'     => '573015639627',
        ], $attrs));
    }

    public function test_search_by_name_returns_matching_contacts(): void
    {
        $this->createContact(['name' => 'María López', 'phone' => '571111111111', 'wa_id' => '571111111111']);
        $this->createContact(['name' => 'Carlos García', 'phone' => '572222222222', 'wa_id' => '572222222222']);

        $response = $this->actingAs($this->agent)->getJson('/api/v1/contacts?search=María');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'María López');
    }

    public function test_search_by_phone_with_formatting_returns_contact(): void
    {
        $this->createContact(['phone' => '573015639627', 'wa_id' => '573015639627']);

        // Formatted phone (with country code, spaces, dashes) should match the stored normalized form
        $response = $this->actingAs($this->agent)->getJson('/api/v1/contacts?search=+57+301+563+9627');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
    }

    public function test_search_by_phone_with_parentheses_returns_contact(): void
    {
        $this->createContact(['phone' => '573015639627', 'wa_id' => '573015639627']);

        $response = $this->actingAs($this->agent)->getJson('/api/v1/contacts?search=(301) 563-9627');

        $response->assertOk();
        $this->assertGreaterThanOrEqual(1, count($response->json('data')));
    }

    public function test_search_with_only_symbols_returns_no_contacts(): void
    {
        $this->createContact(['phone' => '573015639627', 'wa_id' => '573015639627']);
        $this->createContact(['phone' => '571234567890', 'wa_id' => '571234567890', 'name' => 'Otro contacto']);

        // Input with only symbols: phoneSearch becomes "" and must NOT generate "phone ILIKE '%%'"
        $response = $this->actingAs($this->agent)->getJson('/api/v1/contacts?search=' . urlencode('++'));

        $response->assertOk();
        // Neither name nor email matches "++", and phone filter is suppressed → 0 results
        $response->assertJsonCount(0, 'data');
    }

    public function test_search_with_empty_string_returns_all_contacts(): void
    {
        $this->createContact(['phone' => '573015639627', 'wa_id' => '573015639627']);
        $this->createContact(['phone' => '571234567890', 'wa_id' => '571234567890', 'name' => 'Otro contacto']);

        $response = $this->actingAs($this->agent)->getJson('/api/v1/contacts?search=');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    }

    public function test_search_with_dash_only_returns_no_contacts(): void
    {
        $this->createContact(['name' => 'Sin guion', 'phone' => '573015639627', 'wa_id' => '573015639627']);

        $response = $this->actingAs($this->agent)->getJson('/api/v1/contacts?search=-');

        $response->assertOk();
        $response->assertJsonCount(0, 'data');
    }

    public function test_contacts_are_tenant_isolated(): void
    {
        $otherTenant = Tenant::create(['name' => 'Other', 'slug' => 'other']);
        Contact::withoutGlobalScopes()->create([
            'tenant_id' => $otherTenant->id,
            'phone'     => '59900000001',
            'wa_id'     => '59900000001',
            'name'      => 'Contacto ajeno',
        ]);

        $response = $this->actingAs($this->agent)->getJson('/api/v1/contacts');

        $response->assertOk();
        $response->assertJsonCount(0, 'data');
    }
}

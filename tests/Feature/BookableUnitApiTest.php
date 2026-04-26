<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\BookableUnit;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookableUnitApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_crud_bookable_units_and_filter_by_service(): void
    {
        $tenant = Tenant::create(['name' => 'Tenant Units', 'slug' => 'tenant-units']);
        $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);

        $create = $this->actingAs($admin)->postJson('/api/v1/bookable-units', [
            'type' => 'professional',
            'name' => 'Dra. Jurado',
            'capacity' => 1,
            'settings' => ['services' => ['citologia']],
            'is_active' => true,
        ]);

        $create->assertCreated()
            ->assertJsonPath('data.name', 'Dra. Jurado');

        $id = $create->json('data.id');

        $this->actingAs($admin)->getJson('/api/v1/bookable-units?type=professional&service=citologia&active=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $id);

        $this->actingAs($admin)->putJson("/api/v1/bookable-units/{$id}", [
            'type' => 'professional',
            'name' => 'Dra. Actualizada',
            'capacity' => 1,
            'settings' => ['services' => ['colposcopia']],
            'is_active' => false,
        ])->assertOk()->assertJsonPath('data.is_active', false);

        $this->actingAs($admin)->deleteJson("/api/v1/bookable-units/{$id}")
            ->assertNoContent();
    }

    public function test_index_is_tenant_scoped(): void
    {
        $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a']);
        $otherTenant = Tenant::create(['name' => 'Tenant B', 'slug' => 'tenant-b']);
        $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);

        BookableUnit::withoutGlobalScopes()->create([
            'tenant_id' => $otherTenant->id,
            'type' => 'professional',
            'name' => 'Otro tenant',
            'settings' => ['services' => ['citologia']],
        ]);

        $this->actingAs($admin)->getJson('/api/v1/bookable-units')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }
}

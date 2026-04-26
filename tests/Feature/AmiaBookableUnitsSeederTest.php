<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\BookableUnit;
use App\Models\Tenant;
use Database\Seeders\AmiaBookableUnitsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AmiaBookableUnitsSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_is_idempotent_and_deactivates_missing_units(): void
    {
        $tenant = Tenant::unguarded(fn () => Tenant::create([
            'id' => '019d92aa-9b2a-72a3-ad07-d59168920642',
            'name' => 'AMIA',
            'slug' => 'amia',
        ]));

        BookableUnit::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'type' => 'professional',
            'name' => 'Profesional anterior',
            'settings' => ['slug' => 'old-professional'],
            'is_active' => true,
        ]);

        $this->seed(AmiaBookableUnitsSeeder::class);
        $this->seed(AmiaBookableUnitsSeeder::class);

        $this->assertDatabaseCount('bookable_units', 2);
        $this->assertDatabaseHas('bookable_units', [
            'tenant_id' => $tenant->id,
            'name' => 'Profesional AMIA',
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('bookable_units', [
            'tenant_id' => $tenant->id,
            'name' => 'Profesional anterior',
            'is_active' => false,
        ]);
    }
}

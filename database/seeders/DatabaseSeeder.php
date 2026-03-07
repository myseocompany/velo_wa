<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // Create demo tenant (idempotent)
        $tenant = Tenant::firstOrCreate(
            ['slug' => 'demo'],
            [
                'name'         => 'Demo Company',
                'timezone'     => 'America/Bogota',
                'max_agents'   => 10,
                'max_contacts' => 50000,
            ],
        );

        // Owner
        User::firstOrCreate(
            ['email' => 'admin@velo.test'],
            [
                'tenant_id' => $tenant->id,
                'name'      => 'Nicolás Navarro',
                'password'  => bcrypt('password'),
                'role'      => UserRole::Owner,
                'is_active' => true,
            ],
        );

        // Agent
        User::firstOrCreate(
            ['email' => 'agent@velo.test'],
            [
                'tenant_id' => $tenant->id,
                'name'      => 'Agent Demo',
                'password'  => bcrypt('password'),
                'role'      => UserRole::Agent,
                'is_active' => true,
            ],
        );
    }
}

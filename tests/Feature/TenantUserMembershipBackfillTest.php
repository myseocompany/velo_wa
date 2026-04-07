<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TenantUserMembershipBackfillTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_migration_backfills_memberships_from_legacy_users(): void
    {
        $tenantA = Tenant::create([
            'name' => 'Tenant A',
            'slug' => 'tenant-a',
        ]);

        $tenantB = Tenant::create([
            'name' => 'Tenant B',
            'slug' => 'tenant-b',
        ]);

        $owner = User::factory()->owner()->create([
            'tenant_id' => $tenantA->id,
            'email' => 'owner-a@example.com',
            'max_concurrent_conversations' => 7,
            'is_active' => true,
        ]);

        $admin = User::factory()->admin()->create([
            'tenant_id' => $tenantB->id,
            'email' => 'admin-b@example.com',
            'max_concurrent_conversations' => 15,
            'is_active' => false,
        ]);

        User::factory()->create([
            'tenant_id' => null,
            'email' => 'super-admin@example.com',
        ]);

        $deleted = User::factory()->create([
            'tenant_id' => $tenantA->id,
            'email' => 'deleted@example.com',
        ]);
        $deleted->delete();

        Schema::dropIfExists('tenant_user_memberships');
        $this->assertFalse(Schema::hasTable('tenant_user_memberships'));

        $migration = require base_path('database/migrations/2026_04_07_000001_create_tenant_user_memberships_table.php');
        $migration->up();

        $this->assertTrue(Schema::hasTable('tenant_user_memberships'));

        $this->assertDatabaseCount('tenant_user_memberships', 2);

        $this->assertDatabaseHas('tenant_user_memberships', [
            'tenant_id' => $tenantA->id,
            'user_id' => $owner->id,
            'role' => UserRole::Owner->value,
            'is_active' => true,
            'max_concurrent_conversations' => 7,
        ]);

        $this->assertDatabaseHas('tenant_user_memberships', [
            'tenant_id' => $tenantB->id,
            'user_id' => $admin->id,
            'role' => UserRole::Admin->value,
            'is_active' => false,
            'max_concurrent_conversations' => 15,
        ]);

        $this->assertDatabaseMissing('tenant_user_memberships', [
            'user_id' => $deleted->id,
        ]);

        $this->assertSame(1, DB::table('tenant_user_memberships')
            ->where('tenant_id', $tenantA->id)
            ->where('user_id', $owner->id)
            ->count());
    }
}

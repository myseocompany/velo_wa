<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamInviteTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_invite_email_that_exists_in_another_tenant(): void
    {
        $tenantA = Tenant::create([
            'name' => 'Tenant A',
            'slug' => 'tenant-a',
        ]);

        $tenantB = Tenant::create([
            'name' => 'Tenant B',
            'slug' => 'tenant-b',
        ]);

        $admin = User::factory()->admin()->create([
            'tenant_id' => $tenantA->id,
            'email' => 'admin-a@example.com',
        ]);

        User::factory()->create([
            'tenant_id' => $tenantB->id,
            'email' => 'shared@example.com',
        ]);

        $response = $this->actingAs($admin)->postJson('/api/v1/team/invite', [
            'name' => 'Shared User In Tenant A',
            'email' => 'shared@example.com',
            'role' => UserRole::Agent->value,
            'max_concurrent_conversations' => 12,
        ]);

        $response->assertCreated();

        $this->assertDatabaseHas('users', [
            'tenant_id' => $tenantA->id,
            'email' => 'shared@example.com',
            'name' => 'Shared User In Tenant A',
            'role' => UserRole::Agent->value,
            'max_concurrent_conversations' => 12,
        ]);

        $this->assertSame(
            2,
            User::query()->where('email', 'shared@example.com')->count()
        );
    }

    public function test_admin_cannot_invite_duplicate_email_in_same_tenant(): void
    {
        $tenant = Tenant::create([
            'name' => 'Tenant',
            'slug' => 'tenant',
        ]);

        $admin = User::factory()->admin()->create([
            'tenant_id' => $tenant->id,
            'email' => 'admin@example.com',
        ]);

        User::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'dupe@example.com',
        ]);

        $response = $this->actingAs($admin)->postJson('/api/v1/team/invite', [
            'name' => 'Duped User',
            'email' => 'dupe@example.com',
            'role' => UserRole::Agent->value,
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['email']);

        $this->assertSame(
            1,
            User::query()->where('tenant_id', $tenant->id)->where('email', 'dupe@example.com')->count()
        );
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OnboardingExtendedTest extends TestCase
{
    use RefreshDatabase;

    public function test_onboarding_complete_persists_vertical_selection(): void
    {
        $tenant = Tenant::create([
            'name' => 'Tenant Onboarding',
            'slug' => 'tenant-onboarding',
        ]);

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($user)->post('/onboarding/complete', [
            'vertical' => 'restaurant',
        ]);

        $response->assertRedirect('/dashboard');

        $tenant->refresh();
        $this->assertSame('restaurant', $tenant->onboarding_vertical);
        $this->assertNotNull($tenant->onboarding_completed_at);
    }
}


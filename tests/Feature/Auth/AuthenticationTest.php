<?php

namespace Tests\Feature\Auth;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $tenant = Tenant::create(['name' => 'Login Tenant', 'slug' => 'login-tenant']);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_multi_tenant_login_selection_clears_stale_tenant_session_state(): void
    {
        $email = 'shared@example.com';
        $firstTenant = Tenant::create(['name' => 'First Tenant', 'slug' => 'first-tenant']);
        $secondTenant = Tenant::create(['name' => 'Second Tenant', 'slug' => 'second-tenant']);

        User::factory()->create([
            'tenant_id' => $firstTenant->id,
            'email' => $email,
        ]);

        $secondUser = User::factory()->create([
            'tenant_id' => $secondTenant->id,
            'email' => $email,
        ]);

        $this
            ->withSession([
                'url.intended' => '/inbox?line_id=foreign-line',
                'impersonating_user_id' => 'old-user',
                'impersonating_tenant_id' => 'old-tenant',
                'impersonating_admin_id' => 'old-admin',
            ])
            ->post('/login', [
                'email' => $email,
                'password' => 'password',
            ])
            ->assertRedirect(route('login.tenant.select'));

        $this->get(route('login.tenant.select'))
            ->assertOk()
            ->assertSee(str_replace('/', '\\/', route('login.tenant.store', absolute: false)), false);

        $response = $this->post(route('login.tenant.store'), [
            'user_id' => $secondUser->id,
        ]);

        $this->assertAuthenticatedAs($secondUser);
        $response->assertRedirect(route('dashboard'));
        $response->assertSessionMissing('url.intended');
        $response->assertSessionMissing('impersonating_user_id');
        $response->assertSessionMissing('impersonating_tenant_id');
        $response->assertSessionMissing('impersonating_admin_id');
    }

    public function test_authenticated_tenant_switch_clears_stale_tenant_session_state(): void
    {
        $email = 'switcher@example.com';
        $firstTenant = Tenant::create(['name' => 'First Tenant', 'slug' => 'switch-first']);
        $secondTenant = Tenant::create(['name' => 'Second Tenant', 'slug' => 'switch-second']);

        $firstUser = User::factory()->create([
            'tenant_id' => $firstTenant->id,
            'email' => $email,
        ]);

        $secondUser = User::factory()->create([
            'tenant_id' => $secondTenant->id,
            'email' => $email,
        ]);

        $response = $this
            ->actingAs($firstUser)
            ->withSession([
                'url.intended' => '/inbox?line_id=foreign-line',
                'impersonating_user_id' => 'old-user',
                'impersonating_tenant_id' => 'old-tenant',
                'impersonating_admin_id' => 'old-admin',
            ])
            ->post(route('tenant.store'), [
                'user_id' => $secondUser->id,
            ]);

        $this->assertAuthenticatedAs($secondUser);
        $response->assertRedirect(route('dashboard'));
        $response->assertSessionMissing('url.intended');
        $response->assertSessionMissing('impersonating_user_id');
        $response->assertSessionMissing('impersonating_tenant_id');
        $response->assertSessionMissing('impersonating_admin_id');
    }

    public function test_tenant_selector_screen_exposes_the_authenticated_submit_url(): void
    {
        $email = 'switcher@example.com';
        $firstTenant = Tenant::create(['name' => 'First Tenant', 'slug' => 'switch-first']);
        $secondTenant = Tenant::create(['name' => 'Second Tenant', 'slug' => 'switch-second']);

        $user = User::factory()->create([
            'tenant_id' => $firstTenant->id,
            'email' => $email,
        ]);

        User::factory()->create([
            'tenant_id' => $secondTenant->id,
            'email' => $email,
        ]);

        $this->actingAs($user)
            ->get(route('tenant.select'))
            ->assertOk()
            ->assertSee(str_replace('/', '\\/', route('tenant.store', absolute: false)), false);
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }
}

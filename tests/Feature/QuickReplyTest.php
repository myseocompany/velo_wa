<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\QuickReply;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuickReplyTest extends TestCase
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

    public function test_can_list_quick_replies(): void
    {
        QuickReply::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'shortcut'  => 'hola',
            'title'     => 'Saludo',
            'body'      => 'Hola, ¿en qué te puedo ayudar?',
        ]);

        $response = $this->actingAs($this->agent)->getJson('/api/v1/quick-replies');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
    }

    public function test_can_create_quick_reply(): void
    {
        $response = $this->actingAs($this->agent)->postJson('/api/v1/quick-replies', [
            'shortcut' => 'horario',
            'title'    => 'Horario de atención',
            'body'     => 'Atendemos de lunes a viernes de 8am a 6pm.',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('quick_replies', [
            'tenant_id' => $this->tenant->id,
            'shortcut'  => 'horario',
        ]);
    }

    public function test_shortcut_is_normalized_to_lowercase_on_create(): void
    {
        $response = $this->actingAs($this->agent)->postJson('/api/v1/quick-replies', [
            'shortcut' => 'HORARIO',
            'title'    => 'Horario',
            'body'     => 'Atendemos de lunes a viernes.',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('quick_replies', [
            'tenant_id' => $this->tenant->id,
            'shortcut'  => 'horario',
        ]);
        $this->assertDatabaseMissing('quick_replies', [
            'tenant_id' => $this->tenant->id,
            'shortcut'  => 'HORARIO',
        ]);
    }

    public function test_duplicate_shortcut_is_rejected(): void
    {
        QuickReply::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'shortcut'  => 'hola',
            'title'     => 'Saludo',
            'body'      => 'Hola!',
        ]);

        $response = $this->actingAs($this->agent)->postJson('/api/v1/quick-replies', [
            'shortcut' => 'hola',
            'title'    => 'Otro saludo',
            'body'     => 'Hola de nuevo!',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['shortcut']);
    }

    public function test_case_insensitive_duplicate_is_rejected(): void
    {
        QuickReply::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'shortcut'  => 'hola',
            'title'     => 'Saludo',
            'body'      => 'Hola!',
        ]);

        // Backend normalizes "HOLA" → "hola" before uniqueness check
        $response = $this->actingAs($this->agent)->postJson('/api/v1/quick-replies', [
            'shortcut' => 'HOLA',
            'title'    => 'Saludo mayúsculas',
            'body'     => 'Hola en mayúsculas!',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['shortcut']);
    }

    public function test_can_update_quick_reply(): void
    {
        $qr = QuickReply::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'shortcut'  => 'hola',
            'title'     => 'Saludo',
            'body'      => 'Hola!',
        ]);

        $response = $this->actingAs($this->agent)->putJson("/api/v1/quick-replies/{$qr->id}", [
            'shortcut' => 'hola',
            'title'    => 'Saludo actualizado',
            'body'     => 'Hola actualizado!',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('quick_replies', ['id' => $qr->id, 'title' => 'Saludo actualizado']);
    }

    public function test_can_delete_quick_reply(): void
    {
        $qr = QuickReply::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'shortcut'  => 'borrar',
            'title'     => 'Para borrar',
            'body'       => 'Este se borra.',
        ]);

        $response = $this->actingAs($this->agent)->deleteJson("/api/v1/quick-replies/{$qr->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('quick_replies', ['id' => $qr->id]);
    }

    public function test_shortcut_requires_alpha_dash_characters(): void
    {
        $response = $this->actingAs($this->agent)->postJson('/api/v1/quick-replies', [
            'shortcut' => 'hola mundo',
            'title'    => 'Saludo con espacio',
            'body'     => 'Hola!',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['shortcut']);
    }

    public function test_quick_replies_are_tenant_isolated(): void
    {
        $otherTenant = Tenant::create(['name' => 'Other Tenant', 'slug' => 'other-tenant']);
        QuickReply::withoutGlobalScopes()->create([
            'tenant_id' => $otherTenant->id,
            'shortcut'  => 'ajena',
            'title'     => 'Respuesta ajena',
            'body'       => 'No debería verse.',
        ]);

        $response = $this->actingAs($this->agent)->getJson('/api/v1/quick-replies');

        $response->assertOk();
        $response->assertJsonCount(0, 'data');
    }
}

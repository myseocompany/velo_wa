<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Events\ConversationUpdated;
use App\Jobs\SendWhatsAppMessage;
use App\Models\AiAgent;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AiAgentPlaygroundTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Tenant A',
            'slug' => 'tenant-a',
        ]);

        $this->admin = User::factory()->admin()->create([
            'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_admin_can_generate_playground_reply_without_persisting_messages(): void
    {
        Queue::fake();
        Event::fake();
        config(['services.openai.key' => 'test-openai-key']);
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [
                    ['message' => ['content' => 'Respuesta de prueba']],
                ],
            ]),
        ]);

        $agent = $this->aiAgentForTenant($this->tenant, [
            'llm_model' => 'gpt-4o-mini',
        ]);

        $response = $this->actingAs($this->admin)->postJson(
            "/api/v1/ai-agents/{$agent->id}/playground",
            [
                'message' => 'Hola',
                'history' => [
                    ['role' => 'assistant', 'content' => 'Hola, soy Ari.'],
                ],
            ],
        );

        $response->assertOk();
        $response->assertJsonPath('data.reply', 'Respuesta de prueba');
        $response->assertJsonPath('data.provider', 'openai');
        $response->assertJsonPath('data.model', 'gpt-4o-mini');

        $this->assertSame(0, Conversation::withoutGlobalScopes()->count());
        $this->assertSame(0, Message::withoutGlobalScopes()->count());
        Queue::assertNotPushed(SendWhatsAppMessage::class);
        Event::assertNotDispatched(ConversationUpdated::class);
    }

    public function test_playground_returns_422_when_system_prompt_is_empty(): void
    {
        $agent = $this->aiAgentForTenant($this->tenant, [
            'system_prompt' => '',
        ]);

        $response = $this->actingAs($this->admin)->postJson(
            "/api/v1/ai-agents/{$agent->id}/playground",
            ['message' => 'Hola'],
        );

        $response->assertUnprocessable();
        $response->assertJsonPath('message', 'El agente no tiene system_prompt configurado.');
    }

    public function test_playground_returns_structured_502_when_provider_fails(): void
    {
        config(['services.openai.key' => 'test-openai-key']);
        Http::fake([
            'api.openai.com/*' => Http::response(['error' => ['message' => 'server error']], 500),
        ]);

        $agent = $this->aiAgentForTenant($this->tenant, [
            'llm_model' => 'gpt-4o-mini',
        ]);

        $response = $this->actingAs($this->admin)->postJson(
            "/api/v1/ai-agents/{$agent->id}/playground",
            ['message' => 'Hola'],
        );

        $response->assertStatus(502);
        $response->assertJsonPath('provider', 'openai');
        $response->assertJsonPath('status', 500);
        $this->assertIsString($response->json('message'));
        $this->assertArrayNotHasKey('body', $response->json());
    }

    public function test_playground_does_not_fallback_when_configured_provider_key_is_missing(): void
    {
        config([
            'services.anthropic.key' => '',
            'services.openai.key' => 'fallback-key',
            'services.gemini.key' => 'fallback-key',
        ]);

        $agent = $this->aiAgentForTenant($this->tenant, [
            'llm_model' => 'claude-haiku-4-5',
        ]);

        $response = $this->actingAs($this->admin)->postJson(
            "/api/v1/ai-agents/{$agent->id}/playground",
            ['message' => 'Hola'],
        );

        $response->assertStatus(502);
        $response->assertJsonPath('message', 'La API key del proveedor anthropic no está configurada.');
        $response->assertJsonPath('provider', 'anthropic');
        $response->assertJsonPath('status', 0);
        Http::assertNothingSent();
    }

    public function test_playground_returns_status_zero_for_transport_errors(): void
    {
        config(['services.openai.key' => 'test-openai-key']);
        Http::fake(fn () => throw new ConnectionException('timeout'));

        $agent = $this->aiAgentForTenant($this->tenant, [
            'llm_model' => 'gpt-4o-mini',
        ]);

        $response = $this->actingAs($this->admin)->postJson(
            "/api/v1/ai-agents/{$agent->id}/playground",
            ['message' => 'Hola'],
        );

        $response->assertStatus(502);
        $response->assertJsonPath('message', 'No se pudo conectar con openai. Reintenta en unos segundos.');
        $response->assertJsonPath('provider', 'openai');
        $response->assertJsonPath('status', 0);
    }

    public function test_agent_role_cannot_use_playground(): void
    {
        $agentUser = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => UserRole::Agent,
        ]);
        $agent = $this->aiAgentForTenant($this->tenant);

        $response = $this->actingAs($agentUser)->postJson(
            "/api/v1/ai-agents/{$agent->id}/playground",
            ['message' => 'Hola'],
        );

        $response->assertForbidden();
    }

    public function test_cross_tenant_agent_returns_404(): void
    {
        $otherTenant = Tenant::create([
            'name' => 'Tenant B',
            'slug' => 'tenant-b',
        ]);
        $otherAgent = $this->aiAgentForTenant($otherTenant);

        $response = $this->actingAs($this->admin)->postJson(
            "/api/v1/ai-agents/{$otherAgent->id}/playground",
            ['message' => 'Hola'],
        );

        $response->assertNotFound();
    }

    private function aiAgentForTenant(Tenant $tenant, array $overrides = []): AiAgent
    {
        return AiAgent::withoutGlobalScope('tenant')->create([
            'tenant_id' => $tenant->id,
            'name' => 'Agente IA',
            'system_prompt' => 'Responde con claridad.',
            'llm_model' => 'gpt-4o-mini',
            'is_enabled' => true,
            'is_default' => true,
            'context_messages' => 10,
            ...$overrides,
        ]);
    }
}

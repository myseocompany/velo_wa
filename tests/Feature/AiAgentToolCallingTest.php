<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Channel;
use App\Enums\ContactSource;
use App\Enums\ConversationStatus;
use App\Exceptions\AiProviderException;
use App\Models\AiAgent;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Tenant;
use App\Services\AiAgentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiAgentToolCallingTest extends TestCase
{
    use RefreshDatabase;

    public function test_anthropic_tool_loop_preserves_tool_use_and_sends_tool_result(): void
    {
        Config::set('services.anthropic.key', 'test-key');
        [$agent, $conversation] = $this->makeAgentAndConversation();

        Http::fakeSequence()
            ->push([
                'stop_reason' => 'tool_use',
                'content' => [
                    ['type' => 'text', 'text' => 'Reviso tus datos.'],
                    ['type' => 'tool_use', 'id' => 'toolu_123', 'name' => 'get_contact', 'input' => new \stdClass()],
                ],
            ])
            ->push([
                'stop_reason' => 'end_turn',
                'content' => [
                    ['type' => 'text', 'text' => 'Listo, ya tengo tus datos.'],
                ],
            ]);

        $reply = app(AiAgentService::class)->generateReplyWithMeta(
            $agent,
            'prompt',
            [['role' => 'user', 'content' => 'Hola']],
            useTools: true,
            conversation: $conversation,
        );

        $this->assertSame('Listo, ya tengo tus datos.', $reply['reply']);

        Http::assertSentCount(2);
        Http::assertSent(function (Request $request): bool {
            $messages = $request->data()['messages'] ?? [];
            if (count($messages) < 3) {
                return false;
            }

            return ($messages[1]['role'] ?? null) === 'assistant'
                && ($messages[1]['content'][1]['type'] ?? null) === 'tool_use'
                && ($messages[2]['role'] ?? null) === 'user'
                && ($messages[2]['content'][0]['type'] ?? null) === 'tool_result'
                && ($messages[2]['content'][0]['tool_use_id'] ?? null) === 'toolu_123';
        });
    }

    public function test_non_anthropic_provider_with_tools_throws_without_fallback(): void
    {
        [$agent, $conversation] = $this->makeAgentAndConversation(['llm_model' => 'gpt-4o-mini']);

        try {
            app(AiAgentService::class)->generateReplyWithMeta(
                $agent,
                'prompt',
                [['role' => 'user', 'content' => 'Hola']],
                useTools: true,
                conversation: $conversation,
            );
            $this->fail('Expected AiProviderException.');
        } catch (AiProviderException $exception) {
            $this->assertSame('anthropic', $exception->provider);
            $this->assertSame(0, $exception->status);
        }

        Http::assertNothingSent();
    }

    private function makeAgentAndConversation(array $agentOverrides = []): array
    {
        $tenant = Tenant::create(['name' => 'Tenant Tools', 'slug' => 'tenant-tools']);
        $contact = Contact::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'phone' => '573001112233',
            'name' => 'Ana Perez',
            'source' => ContactSource::Manual,
        ]);
        $conversation = Conversation::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'contact_id' => $contact->id,
            'status' => ConversationStatus::Open,
            'channel' => Channel::WhatsApp,
        ]);
        $agent = AiAgent::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'name' => 'AMIA',
            'system_prompt' => 'prompt',
            'llm_model' => 'claude-haiku-4-5',
            'is_enabled' => true,
            'is_default' => true,
            'context_messages' => 10,
            'tool_calling_enabled' => true,
            ...$agentOverrides,
        ]);

        return [$agent, $conversation->load('contact')];
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ConversationStatus;
use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Events\ConversationUpdated;
use App\Exceptions\AiProviderException;
use App\Jobs\SendWhatsAppMessage;
use App\Models\AiAgent;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiAgentService
{
    /**
     * @var array<string, string>
     */
    private const DEFAULT_MODELS_BY_PROVIDER = [
        'anthropic' => 'claude-haiku-4-5',
        'openai' => 'gpt-4o-mini',
        'gemini' => 'gemini-2.0-flash',
    ];

    public function agentForTenant(string $tenantId): ?AiAgent
    {
        $query = AiAgent::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId);

        return (clone $query)
            ->orderByDesc('is_default')
            ->orderByDesc('is_enabled')
            ->orderBy('created_at')
            ->first();
    }

    public function shouldRespond(Conversation $conversation, AiAgent $agent): bool
    {
        if ($conversation->status !== ConversationStatus::Open) {
            return false;
        }

        if ($conversation->ai_agent_enabled !== null) {
            return (bool) $conversation->ai_agent_enabled;
        }

        return (bool) $agent->is_enabled;
    }

    public function generateAndSend(Conversation $conversation, AiAgent $agent, Message $triggeringMessage): void
    {
        if (! $this->shouldRespond($conversation, $agent)) {
            return;
        }

        $prompt = trim((string) ($agent->system_prompt ?? ''));
        if ($prompt === '') {
            Log::warning('AiAgent: empty system prompt', [
                'tenant_id' => $conversation->tenant_id,
                'agent_id' => $agent->id,
            ]);

            return;
        }

        $history = $this->buildContextMessages($conversation, (int) $agent->context_messages);
        if ($history === []) {
            return;
        }

        $reply = $this->generateReply($agent, $prompt, $history);
        if ($reply === null || trim($reply) === '') {
            return;
        }

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'tenant_id' => $conversation->tenant_id,
            'direction' => MessageDirection::Out,
            'body' => trim($reply),
            'status' => MessageStatus::Pending,
            'sent_by' => null,
            'is_automated' => true,
        ]);

        $updates = ['last_message_at' => $message->created_at];
        if ($conversation->first_response_at === null && $conversation->first_message_at !== null) {
            $updates['first_response_at'] = $message->created_at;
        }

        $conversation->increment('message_count', 1, $updates);

        broadcast(new ConversationUpdated($conversation->fresh(['contact', 'assignee', 'messages'])));

        SendWhatsAppMessage::dispatch($message);
    }

    /**
     * @return array<int, array{role: string, content: string}>
     */
    public function buildContextMessages(Conversation $conversation, int $contextMessages): array
    {
        $limit = max(3, min($contextMessages, 50));

        $messages = Message::withoutGlobalScope('tenant')
            ->where('tenant_id', $conversation->tenant_id)
            ->where('conversation_id', $conversation->id)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->reverse()
            ->values();

        $history = [];
        foreach ($messages as $msg) {
            $text = $msg->body;
            if (! $text && $msg->media_type) {
                $text = '[Media: '.$msg->media_type.']';
            }

            $text = trim((string) $text);
            if ($text === '') {
                continue;
            }

            $history[] = [
                'role' => $msg->direction === MessageDirection::In ? 'user' : 'assistant',
                'content' => $text,
            ];
        }

        return $history;
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $history
     */
    public function generateReply(AiAgent $agent, string $prompt, array $history): ?string
    {
        return $this->generateReplyWithMeta($agent, $prompt, $history)['reply'] ?? null;
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $history
     * @param  array<string, mixed>  $context
     * @return array{reply: string, provider: string, model: string}|null
     */
    public function generateReplyWithMeta(
        AiAgent $agent,
        string $prompt,
        array $history,
        bool $disableFallback = false,
        array $context = [],
    ): ?array {
        [$primaryProvider, $primaryModel] = $this->resolveProviderAndModel($agent->llm_model);
        $attempts = $disableFallback
            ? [[$primaryProvider, $primaryModel]]
            : $this->providerFallbackChain($primaryProvider, $primaryModel);
        $lastException = null;

        foreach ($attempts as $index => [$provider, $model]) {
            $apiKey = $this->providerApiKey($provider);
            if ($apiKey === '') {
                $this->throwOrSkipMissingKey($provider, $agent->id, $disableFallback, $context);

                continue;
            }

            try {
                $reply = match ($provider) {
                    'anthropic' => $this->generateAnthropicReply($apiKey, $model, $prompt, $history, $agent->id, $context),
                    'openai' => $this->generateOpenAiReply($apiKey, $model, $prompt, $history, $agent->id, $context),
                    'gemini' => $this->generateGeminiReply($apiKey, $model, $prompt, $history, $agent->id, $context),
                    default => $this->throwProviderRequestException(
                        $provider,
                        $agent->id,
                        0,
                        null,
                        'Proveedor de IA no soportado: '.$provider,
                        $context,
                    ),
                };

                return $reply !== null ? [
                    'reply' => $reply,
                    'provider' => $provider,
                    'model' => $model,
                ] : null;
            } catch (\RuntimeException $exception) {
                $lastException = $exception;
                $isLastAttempt = $index === array_key_last($attempts);

                if (! $this->shouldFallbackAfterProviderFailure($exception) || $isLastAttempt) {
                    throw $exception;
                }

                Log::warning('AiAgent: provider failed, trying fallback provider', [
                    ...$this->logContext($provider, $agent->id, $exception instanceof AiProviderException ? $exception->status : null, $context),
                    'failed_provider' => $provider,
                    'failed_model' => $model,
                    'next_provider' => $attempts[$index + 1][0] ?? null,
                ]);
            }
        }

        if ($lastException instanceof \RuntimeException) {
            throw $lastException;
        }

        Log::warning('AiAgent: no provider available for reply generation', [
            'agent_id' => $agent->id,
            'requested_model' => $agent->llm_model,
            ...$context,
        ]);

        return null;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function resolveProviderAndModel(string $llmModel): array
    {
        $value = trim($llmModel);

        if (str_contains($value, ':')) {
            [$provider, $model] = explode(':', $value, 2);

            return [strtolower(trim($provider)), trim($model)];
        }

        $lower = strtolower($value);

        if (str_starts_with($lower, 'claude')) {
            return ['anthropic', $value];
        }

        if (str_starts_with($lower, 'gpt') || str_starts_with($lower, 'o')) {
            return ['openai', $value];
        }

        if (str_starts_with($lower, 'gemini')) {
            return ['gemini', $value];
        }

        return ['anthropic', $value];
    }

    private function providerApiKey(string $provider): string
    {
        return match ($provider) {
            'anthropic' => (string) config('services.anthropic.key', ''),
            'openai' => (string) config('services.openai.key', ''),
            'gemini' => (string) config('services.gemini.key', ''),
            default => '',
        };
    }

    /**
     * @return array<int, array{0: string, 1: string}>
     */
    private function providerFallbackChain(string $primaryProvider, string $primaryModel): array
    {
        $chain = [[$primaryProvider, $primaryModel]];

        foreach (array_keys(self::DEFAULT_MODELS_BY_PROVIDER) as $provider) {
            if ($provider === $primaryProvider) {
                continue;
            }

            $chain[] = [$provider, self::DEFAULT_MODELS_BY_PROVIDER[$provider]];
        }

        return $chain;
    }

    private function shouldFallbackAfterProviderFailure(\RuntimeException $exception): bool
    {
        if ($exception instanceof AiProviderException) {
            $message = strtolower($exception->getMessage());

            return in_array($exception->status, [0, 429], true)
                || str_contains($message, 'insufficient_quota')
                || str_contains($message, 'rate_limit');
        }

        $message = strtolower($exception->getMessage());

        return str_contains($message, ' api request failed: 429')
            || str_contains($message, 'insufficient_quota')
            || str_contains($message, 'rate_limit')
            || str_contains($message, '429');
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $history
     * @param  array<string, mixed>  $context
     */
    private function generateAnthropicReply(string $apiKey, string $model, string $prompt, array $history, string $agentId, array $context = []): ?string
    {
        try {
            $response = Http::withHeaders([
                'x-api-key' => $apiKey,
                'anthropic-version' => '2023-06-01',
            ])->timeout(40)->post('https://api.anthropic.com/v1/messages', [
                'model' => $model,
                'max_tokens' => 1024,
                'system' => $prompt,
                'messages' => $history,
            ]);
        } catch (ConnectionException $exception) {
            $this->throwProviderRequestException('anthropic', $agentId, 0, null, $exception->getMessage(), $context);
        }

        if ($response->failed()) {
            $this->throwProviderRequestException('anthropic', $agentId, $response->status(), $response->body(), null, $context);
        }

        $content = $response->json('content');
        if (! is_array($content)) {
            return null;
        }

        $parts = [];
        foreach ($content as $item) {
            if (! is_array($item)) {
                continue;
            }

            if (($item['type'] ?? null) === 'text' && is_string($item['text'] ?? null)) {
                $parts[] = $item['text'];
            }
        }

        $text = trim(implode("\n", $parts));

        return $text !== '' ? $text : null;
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $history
     * @param  array<string, mixed>  $context
     */
    private function generateOpenAiReply(string $apiKey, string $model, string $prompt, array $history, string $agentId, array $context = []): ?string
    {
        try {
            $response = Http::withToken($apiKey)
                ->timeout(40)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => $model,
                    'messages' => [
                        ['role' => 'system', 'content' => $prompt],
                        ...$history,
                    ],
                    'max_tokens' => 1024,
                ]);
        } catch (ConnectionException $exception) {
            $this->throwProviderRequestException('openai', $agentId, 0, null, $exception->getMessage(), $context);
        }

        if ($response->failed()) {
            $this->throwProviderRequestException('openai', $agentId, $response->status(), $response->body(), null, $context);
        }

        $content = $response->json('choices.0.message.content');
        if (is_string($content)) {
            $text = trim($content);

            return $text !== '' ? $text : null;
        }

        if (is_array($content)) {
            $parts = [];
            foreach ($content as $part) {
                if (is_array($part) && is_string($part['text'] ?? null)) {
                    $parts[] = $part['text'];
                }
            }

            $text = trim(implode("\n", $parts));

            return $text !== '' ? $text : null;
        }

        return null;
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $history
     * @param  array<string, mixed>  $context
     */
    private function generateGeminiReply(string $apiKey, string $model, string $prompt, array $history, string $agentId, array $context = []): ?string
    {
        $contents = array_map(static function (array $item): array {
            return [
                'role' => $item['role'] === 'assistant' ? 'model' : 'user',
                'parts' => [
                    ['text' => $item['content']],
                ],
            ];
        }, $history);

        try {
            $response = Http::timeout(40)
                ->post('https://generativelanguage.googleapis.com/v1beta/models/'.$model.':generateContent?key='.$apiKey, [
                    'systemInstruction' => [
                        'parts' => [
                            ['text' => $prompt],
                        ],
                    ],
                    'contents' => $contents,
                    'generationConfig' => [
                        'maxOutputTokens' => 1024,
                    ],
                ]);
        } catch (ConnectionException $exception) {
            $this->throwProviderRequestException('gemini', $agentId, 0, null, $exception->getMessage(), $context);
        }

        if ($response->failed()) {
            $this->throwProviderRequestException('gemini', $agentId, $response->status(), $response->body(), null, $context);
        }

        $parts = $response->json('candidates.0.content.parts');
        if (! is_array($parts)) {
            return null;
        }

        $texts = [];
        foreach ($parts as $part) {
            if (is_array($part) && is_string($part['text'] ?? null)) {
                $texts[] = $part['text'];
            }
        }

        $text = trim(implode("\n", $texts));

        return $text !== '' ? $text : null;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function throwOrSkipMissingKey(string $provider, string $agentId, bool $disableFallback, array $context): void
    {
        Log::warning(
            'AiAgent: missing provider API key',
            $this->logContext($provider, $agentId, 0, $context),
        );

        if ($disableFallback) {
            $this->throwProviderRequestException(
                $provider,
                $agentId,
                0,
                null,
                ucfirst($provider).' API key no está configurada.',
                $context,
            );
        }
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function logContext(string $provider, string $agentId, ?int $status, array $extra = []): array
    {
        return array_filter([
            'provider' => $provider,
            'agent_id' => $agentId,
            'status' => $status,
            ...$extra,
        ], static fn ($value) => $value !== null);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function throwProviderRequestException(
        string $provider,
        string $agentId,
        int $status,
        ?string $body,
        ?string $message = null,
        array $context = [],
    ): never {
        Log::error(
            'AiAgent: provider request failed',
            $this->logContext($provider, $agentId, $status, $context),
        );

        throw new AiProviderException(
            $provider,
            $status,
            $body,
            $message ?: ucfirst($provider).' API request failed: '.$status,
        );
    }
}

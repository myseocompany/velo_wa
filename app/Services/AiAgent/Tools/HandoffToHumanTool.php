<?php

declare(strict_types=1);

namespace App\Services\AiAgent\Tools;

use App\Events\ConversationUpdated;
use App\Models\Conversation;

class HandoffToHumanTool implements Tool
{
    public function name(): string
    {
        return 'handoff_to_human';
    }

    public function description(): string
    {
        return 'Desactiva el agente IA para que el equipo humano atienda la conversacion.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'reason' => ['type' => 'string'],
                'urgency' => ['type' => 'string', 'enum' => ['normal', 'high']],
            ],
            'required' => ['reason'],
        ];
    }

    public function execute(Conversation $conversation, array $input): array
    {
        $conversation->update(['ai_agent_enabled' => false]);
        broadcast(new ConversationUpdated($conversation->fresh(['contact', 'assignee', 'messages'])));

        return [
            'ok' => true,
            'ai_disabled' => true,
            'urgency' => $input['urgency'] ?? 'normal',
        ];
    }
}

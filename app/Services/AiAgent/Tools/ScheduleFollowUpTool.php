<?php

declare(strict_types=1);

namespace App\Services\AiAgent\Tools;

use App\Enums\TaskPriority;
use App\Models\Conversation;
use App\Models\Task;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

class ScheduleFollowUpTool implements Tool
{
    public function name(): string
    {
        return 'schedule_follow_up';
    }

    public function description(): string
    {
        return 'Programa una tarea de seguimiento para el equipo humano.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'at' => ['type' => 'string'],
                'topic' => ['type' => 'string'],
            ],
            'required' => ['at', 'topic'],
        ];
    }

    public function execute(Conversation $conversation, array $input): array
    {
        $tenant = Tenant::withoutGlobalScope('tenant')->find($conversation->tenant_id);
        $timezone = $tenant?->timezone ?: 'America/Bogota';
        $at = (string) ($input['at'] ?? 'in_24h');
        $topic = trim((string) ($input['topic'] ?? 'Seguimiento pendiente'));

        $dueAt = match ($at) {
            'in_24h' => CarbonImmutable::now($timezone)->addDay(),
            'tomorrow_morning' => CarbonImmutable::now($timezone)->addDay()->setTime(9, 0),
            default => CarbonImmutable::parse($at, $timezone),
        };

        $task = Task::withoutGlobalScope('tenant')->create([
            'tenant_id' => $conversation->tenant_id,
            'user_id' => null,
            'assigned_to' => $conversation->assigned_to,
            'contact_id' => $conversation->contact_id,
            'conversation_id' => $conversation->id,
            'deal_id' => null,
            'title' => 'Seguimiento: ' . Str::limit($topic, 60),
            'description' => $topic,
            'due_at' => $dueAt,
            'priority' => TaskPriority::Medium,
        ]);

        return [
            'ok' => true,
            'task_id' => $task->id,
            'scheduled_at' => $task->due_at?->toIso8601String(),
        ];
    }
}

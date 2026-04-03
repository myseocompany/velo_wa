<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Task;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TaskReminderDue implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Task $task,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("tenant.{$this->task->tenant_id}")];
    }

    public function broadcastAs(): string
    {
        return 'task.reminder';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->task->id,
            'title' => $this->task->title,
            'due_at' => $this->task->due_at?->toIso8601String(),
            'assigned_to' => $this->task->assigned_to,
            'contact' => $this->task->contact ? [
                'id' => $this->task->contact->id,
                'display_name' => $this->task->contact->displayName(),
            ] : null,
        ];
    }
}

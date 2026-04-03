<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Events\TaskReminderDue;
use App\Models\Task;
use Illuminate\Console\Command;

class SendTaskReminders extends Command
{
    protected $signature = 'tasks:send-reminders';

    protected $description = 'Send in-app reminders for tasks due in the next 30 minutes';

    public function handle(): int
    {
        $tasks = Task::with('contact')
            ->whereNull('completed_at')
            ->whereNull('reminded_at')
            ->whereNotNull('assigned_to')
            ->whereNotNull('due_at')
            ->where('due_at', '>', now())
            ->where('due_at', '<=', now()->addMinutes(30))
            ->get();

        foreach ($tasks as $task) {
            broadcast(new TaskReminderDue($task));

            $task->update(['reminded_at' => now()]);
        }

        $this->info("Sent {$tasks->count()} task reminder(s).");

        return self::SUCCESS;
    }
}

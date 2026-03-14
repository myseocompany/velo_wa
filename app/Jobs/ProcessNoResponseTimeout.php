<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\AutomationTriggerType;
use App\Enums\ConversationStatus;
use App\Models\Automation;
use App\Models\AutomationLog;
use App\Models\Conversation;
use App\Services\AutomationEngineService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessNoResponseTimeout implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(AutomationEngineService $engine): void
    {
        // Get all active no_response_timeout automations grouped by tenant
        $automations = Automation::query()
            ->withoutGlobalScopes()
            ->where('trigger_type', AutomationTriggerType::NoResponseTimeout->value)
            ->where('is_active', true)
            ->get();

        foreach ($automations as $automation) {
            $minutes = (int) ($automation->trigger_config['minutes'] ?? 30);

            // Find conversations that haven't received a response within the timeout window
            $conversations = Conversation::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $automation->tenant_id)
                ->whereIn('status', [ConversationStatus::Open->value, ConversationStatus::Pending->value])
                ->whereNull('first_response_at')
                ->whereNotNull('first_message_at')
                ->where('first_message_at', '<=', now()->subMinutes($minutes))
                ->get();

            // Pre-load conversation IDs that already fired successfully for this automation
            $alreadyFired = AutomationLog::query()
                ->withoutGlobalScopes()
                ->where('automation_id', $automation->id)
                ->where('status', 'success')
                ->pluck('conversation_id')
                ->flip();

            foreach ($conversations as $conversation) {
                if (isset($alreadyFired[$conversation->id])) {
                    continue;
                }

                $engine->dispatchAutomation(
                    $automation,
                    $conversation,
                    AutomationTriggerType::NoResponseTimeout,
                );
            }
        }
    }
}

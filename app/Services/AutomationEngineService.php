<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\Pipeline\MoveDealToStage;
use App\Enums\AutomationActionType;
use App\Services\MenuFormatterService;
use App\Enums\AutomationTriggerType;
use App\Enums\DealStage;
use App\Models\Automation;
use App\Models\AutomationLog;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\PipelineDeal;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;

class AutomationEngineService
{
    private const DEFAULT_TIMEZONE = 'America/Bogota';
    private const LOG_STATUS_FAILED = 'failed';
    private const LOG_STATUS_PROCESSING = 'processing';
    private const LOG_STATUS_SUCCESS = 'success';

    public function __construct(private readonly WhatsAppClientService $waClient) {}

    /**
     * Fire all active automations for a given trigger on a conversation.
     */
    public function dispatch(
        Conversation $conversation,
        AutomationTriggerType $trigger,
        ?Message $message = null,
    ): void {
        $automations = Automation::query()
            ->withoutGlobalScope('tenant')
            ->where('tenant_id', $conversation->tenant_id)
            ->where('trigger_type', $trigger->value)
            ->where('is_active', true)
            ->orderBy('priority')
            ->get();

        foreach ($automations as $automation) {
            $this->dispatchAutomation($automation, $conversation, $trigger, $message);
        }
    }

    /**
     * Fire a specific automation for a conversation.
     */
    public function dispatchAutomation(
        Automation $automation,
        Conversation $conversation,
        AutomationTriggerType $trigger,
        ?Message $message = null,
    ): void {
        $log = null;

        try {
            if ($trigger === AutomationTriggerType::NoResponseTimeout) {
                $log = $this->claimNoResponseTimeoutExecution($automation, $conversation, $trigger);

                if ($log === null) {
                    return;
                }
            }

            if (! $this->matchesTrigger($automation, $conversation, $message)) {
                $this->deleteReservedLog($log);
                return;
            }

            $this->executeAction($automation, $conversation, $message);
            $automation->increment('execution_count');
            $this->writeLog(
                $automation,
                $conversation,
                $trigger,
                self::LOG_STATUS_SUCCESS,
                existingLog: $log,
            );
        } catch (\Throwable $e) {
            Log::error('AutomationEngine: action failed', [
                'automation_id' => $automation->id,
                'trigger'       => $trigger->value,
                'error'         => $e->getMessage(),
            ]);
            $this->writeLog(
                $automation,
                $conversation,
                $trigger,
                self::LOG_STATUS_FAILED,
                $e->getMessage(),
                $log,
            );
        }
    }

    // ─── Trigger matchers ─────────────────────────────────────────────────────

    private function matchesTrigger(Automation $automation, Conversation $conversation, ?Message $message): bool
    {
        return match ($automation->trigger_type) {
            AutomationTriggerType::NewConversation  => true,
            AutomationTriggerType::Keyword          => $this->matchesKeyword($automation, $message),
            AutomationTriggerType::OutsideHours     => $this->isOutsideHours($automation, $conversation),
            AutomationTriggerType::NoResponseTimeout => $this->hasTimedOut($automation, $conversation),
        };
    }

    private function matchesKeyword(Automation $automation, ?Message $message): bool
    {
        if (! $message || ! $message->body) {
            return false;
        }

        $config        = $automation->trigger_config ?? [];
        $keywords      = $config['keywords'] ?? [];
        $matchType     = $config['match_type'] ?? 'any';
        $caseInsensitive = (bool) ($config['case_insensitive'] ?? true);

        if (empty($keywords)) {
            return false;
        }

        $body = $caseInsensitive ? mb_strtolower($message->body) : $message->body;

        $matches = array_filter($keywords, function (string $kw) use ($body, $caseInsensitive): bool {
            $kw = $caseInsensitive ? mb_strtolower($kw) : $kw;
            return str_contains($body, $kw);
        });

        return $matchType === 'all'
            ? count($matches) === count($keywords)
            : count($matches) > 0;
    }

    private function isOutsideHours(Automation $automation, Conversation $conversation): bool
    {
        $tenant   = $conversation->tenant;
        $timezone = $tenant?->timezone ?? self::DEFAULT_TIMEZONE;
        $schedule = $tenant?->business_hours;

        if (empty($schedule)) {
            return false; // No schedule configured → never outside hours
        }

        $now     = CarbonImmutable::now($timezone);
        $dayKey  = strtolower($now->format('D')); // mon, tue, wed…
        $dayConf = $schedule[$dayKey] ?? null;

        if (! $dayConf) {
            return true; // Day not in schedule → outside hours
        }

        $open  = CarbonImmutable::parse($now->format('Y-m-d') . ' ' . $dayConf['open'],  $timezone);
        $close = CarbonImmutable::parse($now->format('Y-m-d') . ' ' . $dayConf['close'], $timezone);

        return $now->lt($open) || $now->gt($close);
    }

    private function hasTimedOut(Automation $automation, Conversation $conversation): bool
    {
        $minutes = (int) ($automation->trigger_config['minutes'] ?? 30);

        if ($conversation->first_response_at !== null) {
            return false; // Already responded
        }

        if (! $conversation->first_message_at) {
            return false;
        }

        return $conversation->first_message_at->diffInMinutes(now()) >= $minutes;
    }

    // ─── Action executors ─────────────────────────────────────────────────────

    private function executeAction(Automation $automation, Conversation $conversation, ?Message $message): void
    {
        match ($automation->action_type) {
            AutomationActionType::SendMessage => $this->actionSendMessage($automation, $conversation),
            AutomationActionType::AssignAgent => $this->actionAssignAgent($automation, $conversation),
            AutomationActionType::AddTag      => $this->actionAddTag($automation, $conversation),
            AutomationActionType::MoveStage   => $this->actionMoveStage($automation, $conversation),
            AutomationActionType::SendMenu    => $this->actionSendMenu($conversation),
        };

        Log::info('AutomationEngine: executed', [
            'automation_id' => $automation->id,
            'action'        => $automation->action_type->value,
            'conversation'  => $conversation->id,
        ]);
    }

    private function actionSendMessage(Automation $automation, Conversation $conversation): void
    {
        $template = $automation->action_config['message'] ?? '';
        if (! $template) {
            return;
        }

        $contact = $conversation->contact;
        $body    = strtr($template, [
            '{{name}}'    => $contact?->name ?? $contact?->push_name ?? 'Cliente',
            '{{phone}}'   => $contact?->phone ?? '',
            '{{company}}' => $contact?->company ?? '',
        ]);

        $msg = Message::create([
            'tenant_id'       => $conversation->tenant_id,
            'conversation_id' => $conversation->id,
            'direction'       => 'out',
            'body'            => $body,
            'status'          => 'pending',
            'is_automated'    => true,
        ]);

        dispatch(new \App\Jobs\SendWhatsAppMessage($msg));
    }

    private function actionAssignAgent(Automation $automation, Conversation $conversation): void
    {
        if ($automation->tenant_id !== $conversation->tenant_id) {
            throw new \LogicException('Automation and conversation tenant mismatch.');
        }

        $agentId = $automation->action_config['agent_id'] ?? null;
        if (! $agentId) {
            return;
        }

        $agent = User::query()
            ->where('tenant_id', $conversation->tenant_id)
            ->where('is_active', true)
            ->find($agentId);

        if (! $agent || $conversation->assigned_to === $agent->id) {
            return;
        }

        $conversation->update([
            'assigned_to' => $agent->id,
            'assigned_at' => now(),
        ]);
    }

    private function actionAddTag(Automation $automation, Conversation $conversation): void
    {
        $tags    = $automation->action_config['tags'] ?? [];
        $contact = $conversation->contact;
        if (! $contact || empty($tags)) {
            return;
        }

        $existing = $contact->tags ?? [];
        $merged   = array_values(array_unique(array_merge($existing, $tags)));
        $contact->update(['tags' => $merged]);
    }

    private function actionMoveStage(Automation $automation, Conversation $conversation): void
    {
        $stageValue = $automation->action_config['stage'] ?? null;
        $stage      = $stageValue ? DealStage::tryFrom($stageValue) : null;
        if (! $stage) {
            return;
        }

        $deal = PipelineDeal::query()
            ->withoutGlobalScope('tenant')
            ->where('tenant_id', $conversation->tenant_id)
            ->where('conversation_id', $conversation->id)
            ->first();

        if ($deal) {
            app(MoveDealToStage::class)->handle($deal, $stage);
        }
    }

    private function actionSendMenu(Conversation $conversation): void
    {
        $tenant   = $conversation->tenant;
        $messages = app(MenuFormatterService::class)->format($tenant);

        foreach ($messages as $body) {
            $msg = Message::create([
                'tenant_id'       => $conversation->tenant_id,
                'conversation_id' => $conversation->id,
                'direction'       => 'out',
                'body'            => $body,
                'status'          => 'pending',
                'is_automated'    => true,
            ]);

            dispatch(new \App\Jobs\SendWhatsAppMessage($msg));
        }
    }

    // ─── Logging ──────────────────────────────────────────────────────────────

    private function writeLog(
        Automation $automation,
        Conversation $conversation,
        AutomationTriggerType $trigger,
        string $status,
        ?string $error = null,
        ?AutomationLog $existingLog = null,
    ): void {
        if ($existingLog !== null) {
            $existingLog->forceFill([
                'status'        => $status,
                'error_message' => $error,
                'triggered_at'  => now(),
            ])->save();

            return;
        }

        AutomationLog::create([
            'tenant_id'       => $automation->tenant_id,
            'automation_id'   => $automation->id,
            'conversation_id' => $conversation->id,
            'trigger_type'    => $trigger->value,
            'action_type'     => $automation->action_type->value,
            'status'          => $status,
            'error_message'   => $error,
            'triggered_at'    => now(),
        ]);
    }

    private function claimNoResponseTimeoutExecution(
        Automation $automation,
        Conversation $conversation,
        AutomationTriggerType $trigger,
    ): ?AutomationLog {
        try {
            return AutomationLog::create([
                'tenant_id'       => $automation->tenant_id,
                'automation_id'   => $automation->id,
                'conversation_id' => $conversation->id,
                'trigger_type'    => $trigger->value,
                'action_type'     => $automation->action_type->value,
                'status'          => self::LOG_STATUS_PROCESSING,
                'triggered_at'    => now(),
            ]);
        } catch (QueryException $e) {
            if ($this->isUniqueConstraintViolation($e)) {
                return null;
            }

            throw $e;
        }
    }

    private function deleteReservedLog(?AutomationLog $log): void
    {
        if ($log === null || ! $log->exists) {
            return;
        }

        $log->delete();
    }

    private function isUniqueConstraintViolation(QueryException $e): bool
    {
        $sqlState = $e->errorInfo[0] ?? null;

        return in_array($sqlState, ['23000', '23505'], true);
    }
}

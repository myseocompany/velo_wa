<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\DealStage;
use App\Models\PipelineDeal;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Notifications\Notification as BaseNotification;
use Illuminate\Notifications\Messages\DatabaseMessage;

class ProcessDueFollowUps implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        // Find active deals whose follow-up time has passed
        $overdue = PipelineDeal::query()
            ->withoutGlobalScopes()
            ->with(['contact', 'assignee'])
            ->whereNotNull('follow_up_at')
            ->where('follow_up_at', '<=', now())
            ->whereNotIn('stage', [DealStage::ClosedWon->value, DealStage::ClosedLost->value])
            ->get();

        foreach ($overdue as $deal) {
            /** @var PipelineDeal $deal */
            $agent = $deal->assignee;

            if ($agent === null) {
                Log::info('FollowUp overdue but no assignee', ['deal_id' => $deal->id]);
                continue;
            }

            // Only notify if the agent has deal notifications enabled (or no preference set)
            $prefs = $agent->notification_preferences ?? [];
            if (isset($prefs['deal_stage_change']) && $prefs['deal_stage_change'] === false) {
                continue;
            }

            $agent->notify(new FollowUpDueNotification($deal));
        }
    }
}

/**
 * In-app (database) notification for a deal follow-up that is due.
 */
class FollowUpDueNotification extends BaseNotification
{
    public function __construct(private readonly PipelineDeal $deal) {}

    public function via(mixed $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(mixed $notifiable): array
    {
        $contact = $this->deal->contact;
        $name    = $contact?->name ?? $contact?->push_name ?? $contact?->phone ?? '—';

        return [
            'type'    => 'follow_up_due',
            'deal_id' => $this->deal->id,
            'title'   => $this->deal->title,
            'contact' => $name,
            'note'    => $this->deal->follow_up_note,
            'stage'   => $this->deal->stage->value,
        ];
    }
}

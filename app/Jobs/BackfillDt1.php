<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Conversations\CalculateDt1;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\QuickReply;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Backfills dt1_minutes_business for historical first conversations.
 *
 * Processes only conversations where:
 * - dt1_minutes_business IS NULL (not yet calculated)
 * - first_message_at IS NOT NULL
 * - The conversation is the contact's first conversation
 *
 * Safe to run multiple times (idempotent — CalculateDt1 checks first_human_response_at).
 *
 * Dispatch: php artisan queue:work --once  (after running the job in tinker)
 * Or via Artisan: App\Jobs\BackfillDt1::dispatch();
 */
class BackfillDt1 implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;
    public int $tries   = 1;

    public function handle(CalculateDt1 $action): void
    {
        $processed = 0;
        $skipped   = 0;

        // Fetch candidate conversations: null dt1, has first_message_at
        Conversation::query()
            ->whereNull('dt1_minutes_business')
            ->whereNotNull('first_message_at')
            ->whereNotNull('first_response_at')
            ->with(['tenant'])
            ->chunkById(100, function ($conversations) use ($action, &$processed, &$skipped): void {
                foreach ($conversations as $conversation) {
                    // Find first human outbound message (not automated, not auto-reply)
                    $humanMessage = Message::query()
                        ->where('conversation_id', $conversation->id)
                        ->where('direction', 'out')
                        ->where('is_automated', false)
                        ->whereNotExists(function ($q) use ($conversation): void {
                            $q->select(\DB::raw(1))
                                ->from('quick_replies')
                                ->where('quick_replies.tenant_id', $conversation->tenant_id)
                                ->where('quick_replies.is_auto_reply', true)
                                ->whereRaw('LOWER(TRIM(quick_replies.body)) = LOWER(TRIM(messages.body))');
                        })
                        ->orderBy('created_at')
                        ->first();

                    if (! $humanMessage) {
                        $skipped++;
                        continue;
                    }

                    $action->handle($conversation, $humanMessage);
                    $processed++;
                }
            });

        Log::info('BackfillDt1: completed', [
            'processed' => $processed,
            'skipped'   => $skipped,
        ]);
    }
}

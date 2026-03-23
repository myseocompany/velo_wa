<?php

declare(strict_types=1);

namespace App\Actions\Pipeline;

use App\Models\PipelineDeal;
use Carbon\CarbonImmutable;

class ScheduleFollowUp
{
    /**
     * Set or clear the follow-up reminder on a deal.
     *
     * Pass null for $followUpAt to clear the reminder.
     */
    public function handle(PipelineDeal $deal, ?CarbonImmutable $followUpAt, ?string $note): PipelineDeal
    {
        $deal->update([
            'follow_up_at'   => $followUpAt,
            'follow_up_note' => $followUpAt !== null ? $note : null,
        ]);

        return $deal->refresh();
    }
}

<?php

declare(strict_types=1);

namespace App\Actions\Pipeline;

use App\Enums\DealStage;
use App\Models\PipelineDeal;
use Carbon\CarbonImmutable;

class MoveDealToStage
{
    public function handle(PipelineDeal $deal, DealStage $stage): PipelineDeal
    {
        if ($deal->stage === $stage) {
            return $deal;
        }

        $now     = CarbonImmutable::now();
        $updates = ['stage' => $stage];

        // Record the first time a deal reaches each stage
        if ($deal->lead_at === null && $stage === DealStage::Lead) {
            $updates['lead_at'] = $now;
        }
        if ($deal->qualified_at === null && $stage === DealStage::Qualified) {
            $updates['qualified_at'] = $now;
        }
        if ($deal->proposal_at === null && $stage === DealStage::Proposal) {
            $updates['proposal_at'] = $now;
        }
        if ($deal->negotiation_at === null && $stage === DealStage::Negotiation) {
            $updates['negotiation_at'] = $now;
        }
        if ($deal->closed_at === null && $stage->isClosed()) {
            $updates['closed_at'] = $now;
        }

        // Clear closed_at when a deal is moved back to an active stage
        if (! $stage->isClosed()) {
            $updates['closed_at'] = null;
        }

        $deal->fill($updates)->save();

        return $deal;
    }
}

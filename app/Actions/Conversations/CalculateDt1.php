<?php

declare(strict_types=1);

namespace App\Actions\Conversations;

use App\Models\Conversation;
use App\Models\Message;
use Carbon\CarbonImmutable;

/**
 * Calculates DT1 (time to first human response) in business minutes.
 *
 * Rules:
 * - Only computed for the contact's FIRST conversation (acquisition metric).
 * - Only computed once (idempotent on first_human_response_at).
 * - Excludes automated messages and quick replies flagged as auto_reply.
 * - Uses tenant's business_hours and timezone; falls back to Mon–Fri 08:00–18:00 / America/Bogota.
 * - If the agent responded before business hours opened → Δt1 = 0 min.
 * - If the agent responded after close → truncates to close.
 *
 * Business hours format:
 *   ['monday' => ['enabled' => true, 'blocks' => [['start' => '08:00', 'end' => '12:00'], ...]]]
 */
class CalculateDt1
{
    private const DEFAULT_TIMEZONE = 'America/Bogota';

    private const DEFAULT_BUSINESS_HOURS = [
        'monday'    => ['enabled' => true,  'blocks' => [['start' => '08:00', 'end' => '18:00']]],
        'tuesday'   => ['enabled' => true,  'blocks' => [['start' => '08:00', 'end' => '18:00']]],
        'wednesday' => ['enabled' => true,  'blocks' => [['start' => '08:00', 'end' => '18:00']]],
        'thursday'  => ['enabled' => true,  'blocks' => [['start' => '08:00', 'end' => '18:00']]],
        'friday'    => ['enabled' => true,  'blocks' => [['start' => '08:00', 'end' => '18:00']]],
        'saturday'  => ['enabled' => false, 'blocks' => [['start' => '08:00', 'end' => '18:00']]],
        'sunday'    => ['enabled' => false, 'blocks' => [['start' => '08:00', 'end' => '18:00']]],
    ];

    public function handle(Conversation $conversation, Message $humanMessage): void
    {
        // Already calculated
        if ($conversation->first_human_response_at !== null) {
            return;
        }

        // Only first conversation of contact
        if (! $this->isFirstConversation($conversation)) {
            return;
        }

        $tInicio = $conversation->first_message_at;
        if (! $tInicio) {
            return;
        }

        $tenant        = $conversation->tenant;
        $timezone      = $this->safeTimezone($tenant?->timezone);
        $businessHours = $this->resolveBusinessHours($tenant?->business_hours);

        $minutes = $this->businessMinutes(
            CarbonImmutable::parse($tInicio)->setTimezone($timezone),
            CarbonImmutable::parse($humanMessage->created_at)->setTimezone($timezone),
            $businessHours,
        );

        $conversation->update([
            'first_human_response_at' => $humanMessage->created_at,
            'dt1_minutes_business'    => $minutes,
        ]);
    }

    // ─── Core algorithm ───────────────────────────────────────────────────────

    private function businessMinutes(
        CarbonImmutable $tInicio,
        CarbonImmutable $tFin,
        array $businessHours,
    ): int {
        $startLab = $this->projectStart($tInicio, $businessHours);
        $endLab   = $this->projectEnd($tFin, $businessHours);

        // Agent responded before any business time elapsed → Δt1 = 0
        if (! $endLab->isAfter($startLab)) {
            return 0;
        }

        return $this->workingMinutes($startLab, $endLab, $businessHours);
    }

    /**
     * Snap tInicio to the start of the next (or current) business slot.
     * - Before first block → snap to that block's start.
     * - Inside a block → take as-is.
     * - Between blocks (lunch break) → snap to next block's start.
     * - After all blocks or non-business day → snap to next business open.
     */
    private function projectStart(CarbonImmutable $t, array $businessHours): CarbonImmutable
    {
        $blocks = $this->getDayBlocks($t, $businessHours);

        if (empty($blocks)) {
            return $this->nextBusinessOpen($t->addDay()->startOfDay(), $businessHours);
        }

        foreach ($blocks as $block) {
            $open  = $t->setTimeFromTimeString($block['start']);
            $close = $t->setTimeFromTimeString($block['end']);

            if ($t->lessThan($open)) {
                return $open; // Before this block → snap to its start
            }

            if ($t->lessThanOrEqualTo($close)) {
                return $t; // Inside this block
            }
            // After this block → check next
        }

        // After all blocks → next business open
        return $this->nextBusinessOpen($t->addDay()->startOfDay(), $businessHours);
    }

    /**
     * Snap tFin to the applicable business boundary:
     * - Inside a block → take as-is.
     * - Before first block → snap to that block's start (→ Δt1 = 0 when ≤ startLab).
     * - Between blocks → truncate to end of previous block.
     * - After all blocks → truncate to last block's end (Case 3).
     * - Non-business day → snap to next business open (→ Δt1 = 0 when ≤ startLab).
     */
    private function projectEnd(CarbonImmutable $t, array $businessHours): CarbonImmutable
    {
        $blocks = $this->getDayBlocks($t, $businessHours);

        if (empty($blocks)) {
            return $this->nextBusinessOpen($t->startOfDay(), $businessHours);
        }

        $prevClose = null;

        foreach ($blocks as $block) {
            $open  = $t->setTimeFromTimeString($block['start']);
            $close = $t->setTimeFromTimeString($block['end']);

            if ($t->lessThan($open)) {
                // Before this block: either snap to its start (before first block)
                // or truncate to previous block's end (during break)
                return $prevClose ?? $open;
            }

            if ($t->lessThanOrEqualTo($close)) {
                return $t; // Inside this block
            }

            $prevClose = $close;
        }

        // After all blocks → truncate to last block's end
        return $prevClose;
    }

    /**
     * Opening time of the next business day from $from (inclusive day check).
     */
    private function nextBusinessOpen(CarbonImmutable $from, array $businessHours): CarbonImmutable
    {
        $cursor = $from->startOfDay();

        for ($i = 0; $i < 7; $i++) {
            $blocks = $this->getDayBlocks($cursor, $businessHours);

            if (! empty($blocks)) {
                return $cursor->setTimeFromTimeString($blocks[0]['start']);
            }

            $cursor = $cursor->addDay()->startOfDay();
        }

        return $from->addDay();
    }

    /**
     * Sum working minutes between two projected business timestamps.
     * Both $startLab and $endLab are guaranteed to be inside valid business slots.
     */
    private function workingMinutes(
        CarbonImmutable $startLab,
        CarbonImmutable $endLab,
        array $businessHours,
    ): int {
        if ($startLab->isSameDay($endLab)) {
            return $this->minutesInDayRange($startLab, $endLab, $businessHours);
        }

        $minutes = 0;

        // Remainder of first day
        $minutes += $this->minutesInDayRange($startLab, $startLab->endOfDay(), $businessHours);

        // Full business days in between
        $cursor = $startLab->addDay()->startOfDay();
        while ($cursor->isBefore($endLab->startOfDay())) {
            $minutes += $this->minutesInFullDay($cursor, $businessHours);
            $cursor   = $cursor->addDay()->startOfDay();
        }

        // Fraction of last day
        $minutes += $this->minutesInDayRange($endLab->startOfDay(), $endLab, $businessHours);

        return max(0, $minutes);
    }

    /**
     * Sum business minutes between $from and $to on the same day,
     * respecting all blocks (handles lunch breaks, etc.).
     */
    private function minutesInDayRange(
        CarbonImmutable $from,
        CarbonImmutable $to,
        array $businessHours,
    ): int {
        $blocks = $this->getDayBlocks($from, $businessHours);
        $total  = 0;

        foreach ($blocks as $block) {
            $blockOpen  = $from->setTimeFromTimeString($block['start']);
            $blockClose = $from->setTimeFromTimeString($block['end']);

            $overlapStart = $from->isAfter($blockOpen) ? $from : $blockOpen;
            $overlapEnd   = $to->isBefore($blockClose) ? $to : $blockClose;

            if ($overlapEnd->isAfter($overlapStart)) {
                $total += (int) $overlapStart->diffInMinutes($overlapEnd);
            }
        }

        return max(0, $total);
    }

    /**
     * Total business minutes in a full day (all blocks summed).
     */
    private function minutesInFullDay(CarbonImmutable $date, array $businessHours): int
    {
        $blocks = $this->getDayBlocks($date, $businessHours);
        $total  = 0;

        foreach ($blocks as $block) {
            [$oh, $om] = array_map('intval', explode(':', $block['start']));
            [$ch, $cm] = array_map('intval', explode(':', $block['end']));
            $total += ($ch * 60 + $cm) - ($oh * 60 + $om);
        }

        return max(0, $total);
    }

    /**
     * Returns sorted blocks for the given day, or empty array if not a business day.
     */
    private function getDayBlocks(CarbonImmutable $t, array $businessHours): array
    {
        $dow = strtolower($t->format('l')); // 'monday', 'tuesday', …
        $day = $businessHours[$dow] ?? null;

        if (! $day || empty($day['enabled']) || empty($day['blocks'])) {
            return [];
        }

        $blocks = $day['blocks'];
        usort($blocks, static fn (array $a, array $b): int => strcmp($a['start'], $b['start']));

        return $blocks;
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function isFirstConversation(Conversation $conversation): bool
    {
        return Conversation::where('contact_id', $conversation->contact_id)
            ->orderBy('created_at')
            ->value('id') === $conversation->id;
    }

    private function resolveBusinessHours(?array $businessHours): array
    {
        if (empty($businessHours)) {
            return self::DEFAULT_BUSINESS_HOURS;
        }

        // Backwards-compat: convert old {start, end} format → new {blocks: [{start, end}]}
        $first = reset($businessHours);
        if (isset($first['start'])) {
            return collect($businessHours)->map(static fn (array $day): array => [
                'enabled' => $day['enabled'] ?? true,
                'blocks'  => [['start' => $day['start'], 'end' => $day['end']]],
            ])->toArray();
        }

        return $businessHours;
    }

    private function safeTimezone(?string $tz): string
    {
        if ($tz && in_array($tz, timezone_identifiers_list(), true)) {
            return $tz;
        }

        return self::DEFAULT_TIMEZONE;
    }
}

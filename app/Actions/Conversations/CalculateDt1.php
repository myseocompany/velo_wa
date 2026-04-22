<?php

declare(strict_types=1);

namespace App\Actions\Conversations;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\QuickReply;
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
 */
class CalculateDt1
{
    private const DEFAULT_TIMEZONE = 'America/Bogota';

    private const DEFAULT_BUSINESS_HOURS = [
        'mon' => ['open' => '08:00', 'close' => '18:00'],
        'tue' => ['open' => '08:00', 'close' => '18:00'],
        'wed' => ['open' => '08:00', 'close' => '18:00'],
        'thu' => ['open' => '08:00', 'close' => '18:00'],
        'fri' => ['open' => '08:00', 'close' => '18:00'],
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
     * Snap t_inicio to the start of the next (or current) business slot.
     * If t is before opening hours on a business day → snap to today's open.
     * If t is after closing hours or on a weekend → snap to next business open.
     */
    private function projectStart(CarbonImmutable $t, array $businessHours): CarbonImmutable
    {
        $dow   = strtolower($t->format('D'));
        $hours = $businessHours[$dow] ?? null;

        if ($hours) {
            $open  = $t->setTimeFromTimeString($hours['open']);
            $close = $t->setTimeFromTimeString($hours['close']);

            if ($t->greaterThanOrEqualTo($open) && $t->lessThanOrEqualTo($close)) {
                return $t; // Inside hours
            }

            if ($t->lessThan($open)) {
                return $open; // Before opening → snap to today's open
            }
            // After closing → fall through to next business open
        }

        return $this->nextBusinessOpen($t->addDay()->startOfDay(), $businessHours);
    }

    /**
     * Snap t_fin to the applicable business boundary:
     * - Inside hours           → take as-is
     * - Before today's opening → snap to today's open (produces Δt1 = 0 when ≤ startLab)
     * - After today's closing  → truncate to today's close (Case 3)
     * - Non-business day       → snap to next business open (produces Δt1 = 0 when ≤ startLab)
     */
    private function projectEnd(CarbonImmutable $t, array $businessHours): CarbonImmutable
    {
        $dow   = strtolower($t->format('D'));
        $hours = $businessHours[$dow] ?? null;

        if ($hours) {
            $open  = $t->setTimeFromTimeString($hours['open']);
            $close = $t->setTimeFromTimeString($hours['close']);

            if ($t->greaterThanOrEqualTo($open) && $t->lessThanOrEqualTo($close)) {
                return $t;
            }

            if ($t->lessThan($open)) {
                return $open; // Before opening → snap to open
            }

            return $close; // After closing → truncate to close
        }

        // Non-business day → snap to next business open
        return $this->nextBusinessOpen($t->startOfDay(), $businessHours);
    }

    /**
     * Returns the opening time of the next business day from $from (inclusive day check).
     */
    private function nextBusinessOpen(CarbonImmutable $from, array $businessHours): CarbonImmutable
    {
        $cursor = $from->startOfDay();

        for ($i = 0; $i < 7; $i++) {
            $dow   = strtolower($cursor->format('D'));
            $hours = $businessHours[$dow] ?? null;

            if ($hours) {
                return $cursor->setTimeFromTimeString($hours['open']);
            }

            $cursor = $cursor->addDay()->startOfDay();
        }

        // Should never reach here with valid business_hours
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
            return max(0, (int) $startLab->diffInMinutes($endLab));
        }

        $minutes = 0;

        // Remainder of first day
        $dow         = strtolower($startLab->format('D'));
        $firstHours  = $businessHours[$dow] ?? null;
        if ($firstHours) {
            $firstClose = $startLab->setTimeFromTimeString($firstHours['close']);
            $minutes   += max(0, (int) $startLab->diffInMinutes($firstClose));
        }

        // Full business days in between
        $cursor = $startLab->addDay()->startOfDay();
        while ($cursor->isBefore($endLab->startOfDay())) {
            $dow   = strtolower($cursor->format('D'));
            $hours = $businessHours[$dow] ?? null;
            if ($hours) {
                [$oh, $om] = array_map('intval', explode(':', $hours['open']));
                [$ch, $cm] = array_map('intval', explode(':', $hours['close']));
                $minutes  += ($ch * 60 + $cm) - ($oh * 60 + $om);
            }
            $cursor = $cursor->addDay()->startOfDay();
        }

        // Fraction of last day
        $dow        = strtolower($endLab->format('D'));
        $lastHours  = $businessHours[$dow] ?? null;
        if ($lastHours) {
            $lastOpen  = $endLab->setTimeFromTimeString($lastHours['open']);
            $minutes  += max(0, (int) $lastOpen->diffInMinutes($endLab));
        }

        return max(0, $minutes);
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
        return (! empty($businessHours)) ? $businessHours : self::DEFAULT_BUSINESS_HOURS;
    }

    private function safeTimezone(?string $tz): string
    {
        if ($tz && in_array($tz, timezone_identifiers_list(), true)) {
            return $tz;
        }

        return self::DEFAULT_TIMEZONE;
    }
}

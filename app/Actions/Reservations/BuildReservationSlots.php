<?php

declare(strict_types=1);

namespace App\Actions\Reservations;

use App\Enums\ReservationStatus;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class BuildReservationSlots
{
    private const DAYS = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];

    public function handle(
        Tenant $tenant,
        CarbonImmutable $baseDate,
        int $days = 7,
        int $durationMinutes = 60,
        int $stepMinutes = 30
    ): array {
        $timezone = $tenant->timezone ?: 'America/Bogota';
        $schedule = $tenant->business_hours ?? $this->defaultBusinessHours();
        $now = CarbonImmutable::now($timezone)->addMinutes(30);

        $maxDays = max(1, min($days, 14));
        $slots = [];

        for ($i = 0; $i < $maxDays; $i++) {
            $day = $baseDate->addDays($i);
            $key = strtolower($day->format('l'));
            $dayConf = $schedule[$key] ?? null;

            if (! is_array($dayConf) || !($dayConf['enabled'] ?? false)) {
                continue;
            }

            $start = $dayConf['start'] ?? '09:00';
            $end = $dayConf['end'] ?? '18:00';
            [$startAt, $endAt] = $this->resolveWindow($day, $start, $end, $timezone);

            $cursor = $startAt;
            while ($cursor->addMinutes($durationMinutes)->lte($endAt)) {
                $slotStart = $cursor;
                $slotEnd = $cursor->addMinutes($durationMinutes);
                $cursor = $cursor->addMinutes($stepMinutes);

                if ($slotStart->lt($now)) {
                    continue;
                }

                if ($this->isOccupied((string) $tenant->id, $slotStart->utc(), $slotEnd->utc())) {
                    continue;
                }

                $slots[] = [
                    'starts_at' => $slotStart->utc()->toIso8601String(),
                    'ends_at' => $slotEnd->utc()->toIso8601String(),
                    'starts_at_local' => $slotStart->toIso8601String(),
                    'ends_at_local' => $slotEnd->toIso8601String(),
                    'label' => $slotStart->translatedFormat('D d MMM, HH:mm') . ' - ' . $slotEnd->format('HH:mm'),
                ];
            }
        }

        return $slots;
    }

    private function resolveWindow(
        CarbonImmutable $day,
        string $start,
        string $end,
        string $timezone
    ): array {
        $startAt = CarbonImmutable::parse($day->format('Y-m-d') . ' ' . $start, $timezone);
        $endAt = CarbonImmutable::parse($day->format('Y-m-d') . ' ' . $end, $timezone);

        if ($endAt->lessThanOrEqualTo($startAt)) {
            $endAt = $endAt->addDay();
        }

        return [$startAt, $endAt];
    }

    private function isOccupied(string $tenantId, CarbonImmutable $slotStartUtc, CarbonImmutable $slotEndUtc): bool
    {
        return DB::table('reservations')
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->whereNotIn('status', [
                ReservationStatus::Cancelled->value,
                ReservationStatus::NoShow->value,
            ])
            ->where('starts_at', '<', $slotEndUtc->toDateTimeString())
            ->where('ends_at', '>', $slotStartUtc->toDateTimeString())
            ->exists();
    }

    private function defaultBusinessHours(): array
    {
        return [
            'monday' => ['enabled' => true, 'start' => '09:00', 'end' => '18:00'],
            'tuesday' => ['enabled' => true, 'start' => '09:00', 'end' => '18:00'],
            'wednesday' => ['enabled' => true, 'start' => '09:00', 'end' => '18:00'],
            'thursday' => ['enabled' => true, 'start' => '09:00', 'end' => '18:00'],
            'friday' => ['enabled' => true, 'start' => '09:00', 'end' => '18:00'],
            'saturday' => ['enabled' => false, 'start' => '09:00', 'end' => '18:00'],
            'sunday' => ['enabled' => false, 'start' => '09:00', 'end' => '18:00'],
        ];
    }
}


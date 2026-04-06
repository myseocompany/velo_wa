<?php

declare(strict_types=1);

namespace App\Actions\Reservations;

use App\Enums\ReservationStatus;
use App\Models\Reservation;
use Carbon\CarbonImmutable;

class MoveReservationToStatus
{
    public function handle(Reservation $reservation, ReservationStatus $status): Reservation
    {
        if ($reservation->status === $status) {
            return $reservation;
        }

        $now = CarbonImmutable::now();
        $updates = ['status' => $status];

        if ($reservation->requested_at === null && $status === ReservationStatus::Requested) {
            $updates['requested_at'] = $now;
        }
        if ($reservation->confirmed_at === null && $status === ReservationStatus::Confirmed) {
            $updates['confirmed_at'] = $now;
        }
        if ($reservation->seated_at === null && $status === ReservationStatus::Seated) {
            $updates['seated_at'] = $now;
        }
        if ($reservation->completed_at === null && $status === ReservationStatus::Completed) {
            $updates['completed_at'] = $now;
            $updates['cancelled_at'] = null;
            $updates['no_show_at'] = null;
        }
        if ($reservation->cancelled_at === null && $status === ReservationStatus::Cancelled) {
            $updates['cancelled_at'] = $now;
            $updates['completed_at'] = null;
            $updates['no_show_at'] = null;
        }
        if ($reservation->no_show_at === null && $status === ReservationStatus::NoShow) {
            $updates['no_show_at'] = $now;
            $updates['completed_at'] = null;
            $updates['cancelled_at'] = null;
        }

        if (! $status->isClosed()) {
            $updates['completed_at'] = null;
            $updates['cancelled_at'] = null;
            $updates['no_show_at'] = null;
        }

        $reservation->fill($updates)->save();

        return $reservation;
    }
}


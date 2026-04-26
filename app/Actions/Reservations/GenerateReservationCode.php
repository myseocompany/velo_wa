<?php

declare(strict_types=1);

namespace App\Actions\Reservations;

use App\Models\Reservation;
use Illuminate\Support\Str;

final class GenerateReservationCode
{
    public function handle(): string
    {
        do {
            $code = 'RES-' . strtoupper(Str::random(6));
        } while (Reservation::query()->where('code', $code)->exists());

        return $code;
    }
}

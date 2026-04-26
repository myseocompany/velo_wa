<?php

declare(strict_types=1);

namespace App\Support;

final class AmiaServiceCatalog
{
    public static function durationFor(string $service): int
    {
        return (int) config(
            'amia.service_durations.' . $service,
            config('amia.default_duration', 60),
        );
    }
}

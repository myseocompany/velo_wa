<?php

declare(strict_types=1);

namespace App\Support;

final class DashboardMetricsCache
{
    public const TTL_MINUTES = 70;

    public static function key(string $tenantId, string $range, bool $businessHours = false): string
    {
        return sprintf('dashboard:%s:%s:%d', $tenantId, $range, $businessHours ? 1 : 0);
    }
}

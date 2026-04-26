<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Message;
use App\Models\Reservation;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AmiaMetrics extends Command
{
    protected $signature = 'amia:metrics {--date= : Date in YYYY-MM-DD, defaults to yesterday}';

    protected $description = 'Print minimal daily AMIA AI/reservation metrics';

    public function handle(): int
    {
        $tenantId = '019d92aa-9b2a-72a3-ad07-d59168920642';
        $tenant = Tenant::withoutGlobalScopes()->find($tenantId);
        if (! $tenant) {
            $this->error('AMIA tenant not found.');
            return self::FAILURE;
        }

        $timezone = $tenant->timezone ?: 'America/Bogota';
        $day = $this->option('date')
            ? CarbonImmutable::parse((string) $this->option('date'), $timezone)
            : CarbonImmutable::now($timezone)->subDay();
        $start = $day->startOfDay()->utc();
        $end = $day->endOfDay()->utc();

        $aiMessages = Message::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->where('is_automated', true)
            ->whereBetween('created_at', [$start, $end])
            ->count();

        $createdByConversation = Reservation::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->whereNotNull('conversation_id')
            ->whereBetween('created_at', [$start, $end])
            ->count();

        $createdTotal = Reservation::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$start, $end])
            ->count();

        $confirmedRequested = Reservation::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->whereNotNull('conversation_id')
            ->whereNotNull('confirmed_at')
            ->whereBetween('created_at', [$start, $end])
            ->count();

        $byService = Reservation::withoutGlobalScope('tenant')
            ->select('service', DB::raw('count(*) as total'))
            ->where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$start, $end])
            ->groupBy('service')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row): array => [
                'service' => $row->service ?: '(sin servicio)',
                'total' => $row->total,
            ]);

        $this->info('AMIA metrics for '.$day->toDateString());
        $this->table(['metric', 'value'], [
            ['automated_messages', $aiMessages],
            ['reservations_with_conversation', $createdByConversation],
            ['reservations_total', $createdTotal],
            ['conversation_reservations_confirmed', $confirmedRequested],
        ]);
        $this->table(['service', 'reservations'], $byService->all());

        return self::SUCCESS;
    }
}

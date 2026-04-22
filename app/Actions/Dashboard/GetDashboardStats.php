<?php

declare(strict_types=1);

namespace App\Actions\Dashboard;

use App\Enums\ConversationStatus;
use App\Enums\DealStage;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\PipelineDeal;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GetDashboardStats
{
    private const DEFAULT_TIMEZONE = 'America/Bogota';

    /** @var array<string, string> */
    private const ALLOWED_RANGES = [
        'horas'     => 'horas',
        'semana'    => 'semana',
        'mes'       => 'mes',
        'trimestre' => 'trimestre',
        'ano'       => 'ano',
    ];

    /** @var array<string, int> */
    private const DAY_MAP = [
        'sun' => 0, 'mon' => 1, 'tue' => 2,
        'wed' => 3, 'thu' => 4, 'fri' => 5, 'sat' => 6,
    ];

    public function handle(User $user, string $range, bool $businessHoursOnly = false): array
    {
        $timezone = $this->safeTimezone($user->tenant?->timezone);
        $range    = $this->sanitizeRange($range);

        [$startAtLocal, $endAtLocal, $bucket] = $this->resolveRangeWindow($range, $timezone);

        $startUtc = $startAtLocal->setTimezone('UTC');
        $endUtc   = $endAtLocal->setTimezone('UTC');

        return [
            'stats'                  => $this->computeStats($timezone, $startUtc, $endUtc),
            'dt1_stats'              => $businessHoursOnly
                ? $this->computeDt1StatsBusinessHours($startUtc, $endUtc, $user->tenant?->business_hours, $timezone)
                : $this->computeDt1Stats($startUtc, $endUtc),
            'conversation_chart'     => $this->buildConversationChart($startAtLocal, $endAtLocal, $bucket, $range, $timezone),
            'messages_chart'         => $this->buildMessagesChart($startAtLocal, $endAtLocal, $bucket, $timezone),
            'pipeline_summary'       => $this->buildPipelineSummary(),
            'recent_conversations'   => $this->buildRecentConversations($timezone),
            'business_hours_active'  => $businessHoursOnly,
        ];
    }

    // ─── Stats cards ──────────────────────────────────────────────────────────

    private function computeStats(string $timezone, CarbonImmutable $startUtc, CarbonImmutable $endUtc): array
    {
        // Conversations opened (created) within the selected period that are still open/pending
        $openConversations = Conversation::query()
            ->whereIn('status', [ConversationStatus::Open->value, ConversationStatus::Pending->value])
            ->whereBetween('created_at', [$startUtc, $endUtc])
            ->count();

        // Conversations closed within the period — regardless of current status (handles reopen cases)
        $closedInPeriod = Conversation::query()
            ->whereNotNull('closed_at')
            ->whereBetween('closed_at', [$startUtc, $endUtc])
            ->count();

        // Contacts created within the selected period
        $totalContacts = Contact::query()
            ->whereBetween('created_at', [$startUtc, $endUtc])
            ->count();

        // Messages sent/received within the selected period
        $messagesInPeriod = Message::query()
            ->whereBetween('created_at', [$startUtc, $endUtc])
            ->count();

        $inboundInPeriod = Message::query()
            ->where('direction', 'in')
            ->whereBetween('created_at', [$startUtc, $endUtc])
            ->count();

        return [
            'open_conversations' => $openConversations,
            'closed_in_period'   => $closedInPeriod,
            'total_contacts'     => $totalContacts,
            'messages_today'     => $messagesInPeriod,
            'inbound_today'      => $inboundInPeriod,
        ];
    }

    // ─── Dt1 P50 / P95 ───────────────────────────────────────────────────────

    private function computeDt1Stats(CarbonImmutable $startUtc, CarbonImmutable $endUtc): array
    {
        // Use dt1_minutes_business (precise, business-hours-aware) when available;
        // fall back to raw wall-clock seconds for historical rows that predate the migration.
        $row = Conversation::query()
            ->whereNotNull('first_message_at')
            ->where(function ($q): void {
                $q->whereNotNull('dt1_minutes_business')
                  ->orWhereNotNull('first_response_at');
            })
            ->whereBetween('created_at', [$startUtc, $endUtc])
            ->whereNotExists(function ($q): void {
                // Exclude conversations whose contact has any tag marked exclude_from_metrics
                $q->select(DB::raw(1))
                    ->from('contact_tag')
                    ->join('tags', 'tags.id', '=', 'contact_tag.tag_id')
                    ->whereColumn('contact_tag.contact_id', 'conversations.contact_id')
                    ->where('tags.exclude_from_metrics', true);
            })
            ->selectRaw("
                COUNT(*) as total,
                ROUND(AVG(
                    COALESCE(dt1_minutes_business * 60, EXTRACT(EPOCH FROM (first_response_at - first_message_at)))
                ))::int AS avg_dt1,
                PERCENTILE_CONT(0.5) WITHIN GROUP (ORDER BY
                    COALESCE(dt1_minutes_business * 60, EXTRACT(EPOCH FROM (first_response_at - first_message_at)))
                ) AS median_dt1,
                PERCENTILE_CONT(0.95) WITHIN GROUP (ORDER BY
                    COALESCE(dt1_minutes_business * 60, EXTRACT(EPOCH FROM (first_response_at - first_message_at)))
                ) AS p95_dt1
            ")
            ->first();

        if (! $row || (int) $row->total === 0) {
            return ['avg' => null, 'median' => null, 'p95' => null, 'total' => 0];
        }

        return [
            'avg'    => (int) round((float) $row->avg_dt1),
            'median' => (int) round((float) $row->median_dt1),
            'p95'    => (int) round((float) $row->p95_dt1),
            'total'  => (int) $row->total,
        ];
    }

    /**
     * Dt1 filtered to conversations that started within business hours.
     * Uses the tenant's business_hours config or defaults to Mon–Fri 09:00–18:00.
     *
     * @param array<string, array{open: string, close: string}>|null $businessHours
     */
    private function computeDt1StatsBusinessHours(
        CarbonImmutable $startUtc,
        CarbonImmutable $endUtc,
        ?array $businessHours,
        string $timezone,
    ): array {
        $conditions = $this->buildBusinessHoursConditions($businessHours, $timezone);

        if (empty($conditions)) {
            return $this->computeDt1Stats($startUtc, $endUtc);
        }

        $whereClause = '(' . implode(' OR ', $conditions) . ')';

        $row = Conversation::query()
            ->whereNotNull('first_message_at')
            ->where(function ($q): void {
                $q->whereNotNull('dt1_minutes_business')
                  ->orWhereNotNull('first_response_at');
            })
            ->whereBetween('created_at', [$startUtc, $endUtc])
            ->whereRaw($whereClause)
            ->whereNotExists(function ($q): void {
                $q->select(DB::raw(1))
                    ->from('contact_tag')
                    ->join('tags', 'tags.id', '=', 'contact_tag.tag_id')
                    ->whereColumn('contact_tag.contact_id', 'conversations.contact_id')
                    ->where('tags.exclude_from_metrics', true);
            })
            ->selectRaw("
                COUNT(*) as total,
                ROUND(AVG(
                    COALESCE(dt1_minutes_business * 60, EXTRACT(EPOCH FROM (first_response_at - first_message_at)))
                ))::int AS avg_dt1,
                PERCENTILE_CONT(0.5) WITHIN GROUP (ORDER BY
                    COALESCE(dt1_minutes_business * 60, EXTRACT(EPOCH FROM (first_response_at - first_message_at)))
                ) AS median_dt1,
                PERCENTILE_CONT(0.95) WITHIN GROUP (ORDER BY
                    COALESCE(dt1_minutes_business * 60, EXTRACT(EPOCH FROM (first_response_at - first_message_at)))
                ) AS p95_dt1
            ")
            ->first();

        if (! $row || (int) $row->total === 0) {
            return ['avg' => null, 'median' => null, 'p95' => null, 'total' => 0];
        }

        return [
            'avg'    => (int) round((float) $row->avg_dt1),
            'median' => (int) round((float) $row->median_dt1),
            'p95'    => (int) round((float) $row->p95_dt1),
            'total'  => (int) $row->total,
        ];
    }

    /**
     * Builds SQL OR conditions for each configured business-hours slot.
     *
     * @param  array<string, array{open: string, close: string}>|null $businessHours
     * @return list<string>
     */
    private function buildBusinessHoursConditions(?array $businessHours, string $timezone): array
    {
        // Default: Mon–Fri, 08:00–18:00
        $schedule = $businessHours && count($businessHours) > 0
            ? $businessHours
            : ['mon' => ['open' => '08:00', 'close' => '18:00'],
               'tue' => ['open' => '08:00', 'close' => '18:00'],
               'wed' => ['open' => '08:00', 'close' => '18:00'],
               'thu' => ['open' => '08:00', 'close' => '18:00'],
               'fri' => ['open' => '08:00', 'close' => '18:00']];

        $conditions = [];

        foreach ($schedule as $day => $hours) {
            $dow = self::DAY_MAP[strtolower($day)] ?? null;
            if ($dow === null) {
                continue;
            }
            $open  = $hours['open']  ?? null;
            $close = $hours['close'] ?? null;

            if (! $open || ! $close) {
                continue;
            }
            // Validate time format to prevent SQL injection
            if (! preg_match('/^\d{2}:\d{2}$/', $open) || ! preg_match('/^\d{2}:\d{2}$/', $close)) {
                continue;
            }

            // PostgreSQL: EXTRACT(DOW FROM ...) → 0=Sunday, 1=Monday … 6=Saturday
            $conditions[] = sprintf(
                "(EXTRACT(DOW FROM (first_message_at AT TIME ZONE 'UTC' AT TIME ZONE '%s'))::int = %d"
                . " AND CAST(first_message_at AT TIME ZONE 'UTC' AT TIME ZONE '%s' AS TIME) BETWEEN '%s'::time AND '%s'::time)",
                addslashes($timezone), $dow, addslashes($timezone), $open, $close,
            );
        }

        return $conditions;
    }

    // ─── Conversations chart (SQL aggregation) ────────────────────────────────

    private function buildConversationChart(
        CarbonImmutable $startAt,
        CarbonImmutable $endAt,
        string $bucket,
        string $range,
        string $timezone,
    ): array {
        $series = $this->buildConversationSeries($startAt, $endAt, $bucket, $range, $timezone);

        return [
            'range'  => $range,
            'ranges' => [
                ['key' => 'horas',     'label' => 'Hoy'],
                ['key' => 'semana',    'label' => '7 días'],
                ['key' => 'mes',       'label' => 'Mes'],
                ['key' => 'trimestre', 'label' => 'Trimestre'],
                ['key' => 'ano',       'label' => 'Año'],
            ],
            'series'   => $series,
            'total'    => (int) array_sum(array_column($series, 'conversations')),
            'timezone' => $timezone,
        ];
    }

    private function buildConversationSeries(
        CarbonImmutable $startAt,
        CarbonImmutable $endAt,
        string $bucket,
        string $range,
        string $timezone,
    ): array {
        $truncUnit = $this->pgTruncUnit($bucket);

        // SQL-side aggregation — avoids loading all rows into PHP
        $expr = "DATE_TRUNC('{$truncUnit}', created_at AT TIME ZONE 'UTC' AT TIME ZONE '{$timezone}')";

        $rows = DB::table('conversations')
            ->selectRaw("{$expr} AS bkt, COUNT(*) AS cnt")
            ->where('tenant_id', auth()->user()->tenant_id)
            ->whereBetween('created_at', [$startAt->setTimezone('UTC'), $endAt->setTimezone('UTC')])
            ->groupByRaw($expr)
            ->get()
            ->keyBy(fn ($r) => CarbonImmutable::parse($r->bkt)->setTimezone($timezone)->format('Y-m-d H:i:s'));

        $series = [];
        $cursor = $this->truncateToBucket($startAt, $bucket);

        while ($cursor->lessThanOrEqualTo($endAt)) {
            $key      = $cursor->format('Y-m-d H:i:s');
            $series[] = [
                'bucket_start'  => $cursor->toIso8601String(),
                'label'         => $this->formatLabel($cursor, $bucket, $range),
                'conversations' => (int) ($rows->get($key)?->cnt ?? 0),
            ];
            $cursor = $this->advanceBucket($cursor, $bucket);
        }

        return $series;
    }

    // ─── Messages in/out chart (SQL aggregation) ──────────────────────────────

    private function buildMessagesChart(
        CarbonImmutable $startAt,
        CarbonImmutable $endAt,
        string $bucket,
        string $timezone,
    ): array {
        $truncUnit = $this->pgTruncUnit($bucket);

        // SQL-side aggregation grouped by bucket + direction
        $expr = "DATE_TRUNC('{$truncUnit}', created_at AT TIME ZONE 'UTC' AT TIME ZONE '{$timezone}')";

        $rows = DB::table('messages')
            ->selectRaw("{$expr} AS bkt, direction, COUNT(*) AS cnt")
            ->where('tenant_id', auth()->user()->tenant_id)
            ->whereBetween('created_at', [$startAt->setTimezone('UTC'), $endAt->setTimezone('UTC')])
            ->groupByRaw("{$expr}, direction")
            ->get();

        // Index by bucket → direction → count
        $indexed = [];
        foreach ($rows as $row) {
            $bktKey = CarbonImmutable::parse($row->bkt)->setTimezone($timezone)->format('Y-m-d H:i:s');
            $indexed[$bktKey][$row->direction] = (int) $row->cnt;
        }

        $series = [];
        $cursor = $this->truncateToBucket($startAt, $bucket);

        while ($cursor->lessThanOrEqualTo($endAt)) {
            $key      = $cursor->format('Y-m-d H:i:s');
            $series[] = [
                'label'    => $this->formatLabel($cursor, $bucket, 'messages'),
                'inbound'  => $indexed[$key]['in']  ?? 0,
                'outbound' => $indexed[$key]['out'] ?? 0,
            ];
            $cursor = $this->advanceBucket($cursor, $bucket);
        }

        $flat = $series;

        return [
            'series'         => $flat,
            'total_inbound'  => array_sum(array_column($flat, 'inbound')),
            'total_outbound' => array_sum(array_column($flat, 'outbound')),
        ];
    }

    // ─── Pipeline summary ─────────────────────────────────────────────────────

    private function buildPipelineSummary(): array
    {
        $rows = PipelineDeal::query()
            ->selectRaw('stage, COUNT(*) as count, COALESCE(SUM(value), 0) as total_value')
            ->groupBy('stage')
            ->get()
            ->keyBy('stage');

        $activeStages   = DealStage::activeStages();
        $activePipeline = 0.0;
        $activeDeals    = 0;

        foreach ($activeStages as $stage) {
            $row             = $rows->get($stage->value);
            $activePipeline += (float) ($row?->total_value ?? 0);
            $activeDeals    += (int) ($row?->count ?? 0);
        }

        return [
            'active_deals'    => $activeDeals,
            'active_pipeline' => $activePipeline,
            'total_won'       => (float) ($rows->get('closed_won')?->total_value ?? 0),
            'deals_won'       => (int) ($rows->get('closed_won')?->count ?? 0),
            'deals_lost'      => (int) ($rows->get('closed_lost')?->count ?? 0),
        ];
    }

    // ─── Recent conversations ─────────────────────────────────────────────────

    private function buildRecentConversations(string $timezone): array
    {
        return Conversation::query()
            ->whereHas('messages')
            ->with([
                'contact:id,name,push_name,phone',
                'messages' => fn ($q) => $q->reorder()->orderByDesc('created_at')->limit(1),
            ])
            ->orderByDesc('last_message_at')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(function (Conversation $conversation) use ($timezone): array {
                $lastMessage = $conversation->messages->first();

                return [
                    'id'              => $conversation->id,
                    'contact_name'    => $conversation->contact?->displayName() ?? 'Sin nombre',
                    'last_message'    => Str::limit($lastMessage?->body ?: '📎 Archivo', 80),
                    'last_message_at' => $lastMessage?->created_at?->setTimezone($timezone)?->toIso8601String(),
                    'status'          => $conversation->status->value,
                ];
            })
            ->values()
            ->all();
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function sanitizeRange(string $range): string
    {
        return array_key_exists($range, self::ALLOWED_RANGES) ? $range : 'semana';
    }

    private function safeTimezone(?string $tz): string
    {
        if ($tz && in_array($tz, timezone_identifiers_list(), true)) {
            return $tz;
        }

        return self::DEFAULT_TIMEZONE;
    }

    /** @return array{CarbonImmutable, CarbonImmutable, string} */
    private function resolveRangeWindow(string $range, string $timezone): array
    {
        $now = CarbonImmutable::now($timezone);

        return match ($range) {
            'horas'     => [$now->startOfDay(),                            $now->endOfDay(), 'hour'],
            'semana'    => [$now->subDays(6)->startOfDay(),                $now->endOfDay(), 'day'],
            'mes'       => [$now->startOfMonth(),                          $now->endOfDay(), 'day'],
            'trimestre' => [$now->subMonthsNoOverflow(2)->startOfMonth(),  $now->endOfDay(), 'week'],
            'ano'       => [$now->subMonthsNoOverflow(11)->startOfMonth(), $now->endOfDay(), 'month'],
            default     => [$now->subDays(6)->startOfDay(),                $now->endOfDay(), 'day'],
        };
    }

    private function pgTruncUnit(string $bucket): string
    {
        return match ($bucket) {
            'hour'  => 'hour',
            'week'  => 'week',
            'month' => 'month',
            default => 'day',
        };
    }

    private function truncateToBucket(CarbonImmutable $value, string $bucket): CarbonImmutable
    {
        return match ($bucket) {
            'hour'  => $value->startOfHour(),
            'day'   => $value->startOfDay(),
            'week'  => $value->startOfWeek(),
            'month' => $value->startOfMonth(),
            default => $value->startOfDay(),
        };
    }

    private function advanceBucket(CarbonImmutable $value, string $bucket): CarbonImmutable
    {
        return match ($bucket) {
            'hour'  => $value->addHour(),
            'day'   => $value->addDay(),
            'week'  => $value->addWeek(),
            'month' => $value->addMonth(),
            default => $value->addDay(),
        };
    }

    private function formatLabel(CarbonImmutable $value, string $bucket, string $range): string
    {
        return match ($bucket) {
            'hour'  => $value->format('H:i'),
            'week'  => $value->format('d/m'),
            'month' => $range === 'ano' ? $value->format('m/Y') : $value->format('M Y'),
            default => $value->format('d/m'),
        };
    }
}

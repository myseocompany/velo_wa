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

    public function handle(User $user, string $range): array
    {
        $timezone = $user->tenant?->timezone ?: self::DEFAULT_TIMEZONE;
        $range    = $this->sanitizeRange($range);

        [$startAtLocal, $endAtLocal, $bucket] = $this->resolveRangeWindow($range, $timezone);

        $startUtc = $startAtLocal->setTimezone('UTC');
        $endUtc   = $endAtLocal->setTimezone('UTC');

        return [
            'stats'                => $this->computeStats($timezone, $startUtc, $endUtc),
            'dt1_stats'            => $this->computeDt1Stats($startUtc, $endUtc),
            'conversation_chart'   => $this->buildConversationChart($startAtLocal, $endAtLocal, $bucket, $range, $timezone),
            'messages_chart'       => $this->buildMessagesChart($startAtLocal, $endAtLocal, $bucket, $timezone),
            'pipeline_summary'     => $this->buildPipelineSummary(),
            'recent_conversations' => $this->buildRecentConversations($timezone),
        ];
    }

    // ─── Stats cards ──────────────────────────────────────────────────────────

    private function computeStats(string $timezone, CarbonImmutable $startUtc, CarbonImmutable $endUtc): array
    {
        $openConversations = Conversation::query()
            ->whereIn('status', [ConversationStatus::Open->value, ConversationStatus::Pending->value])
            ->count();

        $closedInPeriod = Conversation::query()
            ->where('status', ConversationStatus::Closed->value)
            ->whereBetween('closed_at', [$startUtc, $endUtc])
            ->count();

        $totalContacts = Contact::query()->count();

        $todayStart    = CarbonImmutable::now($timezone)->startOfDay()->setTimezone('UTC');
        $todayEnd      = CarbonImmutable::now($timezone)->endOfDay()->setTimezone('UTC');
        $messagesToday = Message::query()->whereBetween('created_at', [$todayStart, $todayEnd])->count();

        $inboundToday = Message::query()
            ->where('direction', 'in')
            ->whereBetween('created_at', [$todayStart, $todayEnd])
            ->count();

        return [
            'open_conversations' => $openConversations,
            'closed_in_period'   => $closedInPeriod,
            'total_contacts'     => $totalContacts,
            'messages_today'     => $messagesToday,
            'inbound_today'      => $inboundToday,
        ];
    }

    // ─── Dt1 P50 / P95 ───────────────────────────────────────────────────────

    private function computeDt1Stats(CarbonImmutable $startUtc, CarbonImmutable $endUtc): array
    {
        $row = Conversation::query()
            ->whereNotNull('first_message_at')
            ->whereNotNull('first_response_at')
            ->whereBetween('created_at', [$startUtc, $endUtc])
            ->selectRaw("
                COUNT(*) as total,
                ROUND(AVG(EXTRACT(EPOCH FROM (first_response_at - first_message_at))))::int AS avg_dt1,
                PERCENTILE_CONT(0.5)  WITHIN GROUP (ORDER BY EXTRACT(EPOCH FROM (first_response_at - first_message_at))) AS median_dt1,
                PERCENTILE_CONT(0.95) WITHIN GROUP (ORDER BY EXTRACT(EPOCH FROM (first_response_at - first_message_at))) AS p95_dt1
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

    // ─── Conversations chart ──────────────────────────────────────────────────

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
        $series = [];
        $cursor = $this->truncateToBucket($startAt, $bucket);

        while ($cursor->lessThanOrEqualTo($endAt)) {
            $key          = $cursor->format('Y-m-d H:i:s');
            $series[$key] = [
                'bucket_start'  => $cursor->toIso8601String(),
                'label'         => $this->formatLabel($cursor, $bucket, $range),
                'conversations' => 0,
            ];
            $cursor = $this->advanceBucket($cursor, $bucket);
        }

        $conversations = Conversation::query()
            ->whereBetween('created_at', [$startAt->setTimezone('UTC'), $endAt->setTimezone('UTC')])
            ->get(['created_at']);

        foreach ($conversations as $conversation) {
            if (! $conversation->created_at) {
                continue;
            }
            $bucketStart = $this->truncateToBucket(
                CarbonImmutable::instance($conversation->created_at)->setTimezone($timezone),
                $bucket,
            );
            $key = $bucketStart->format('Y-m-d H:i:s');
            if (isset($series[$key])) {
                $series[$key]['conversations']++;
            }
        }

        return array_values($series);
    }

    // ─── Messages in/out chart ────────────────────────────────────────────────

    private function buildMessagesChart(
        CarbonImmutable $startAt,
        CarbonImmutable $endAt,
        string $bucket,
        string $timezone,
    ): array {
        $series = [];
        $cursor = $this->truncateToBucket($startAt, $bucket);

        while ($cursor->lessThanOrEqualTo($endAt)) {
            $key          = $cursor->format('Y-m-d H:i:s');
            $series[$key] = [
                'label'    => $this->formatLabel($cursor, $bucket, 'messages'),
                'inbound'  => 0,
                'outbound' => 0,
            ];
            $cursor = $this->advanceBucket($cursor, $bucket);
        }

        $messages = Message::query()
            ->whereBetween('created_at', [$startAt->setTimezone('UTC'), $endAt->setTimezone('UTC')])
            ->get(['created_at', 'direction']);

        foreach ($messages as $msg) {
            if (! $msg->created_at) {
                continue;
            }
            $bucketStart = $this->truncateToBucket(
                CarbonImmutable::instance($msg->created_at)->setTimezone($timezone),
                $bucket,
            );
            $key = $bucketStart->format('Y-m-d H:i:s');
            if (! isset($series[$key])) {
                continue;
            }
            if ($msg->direction === 'in') {
                $series[$key]['inbound']++;
            } else {
                $series[$key]['outbound']++;
            }
        }

        $flat = array_values($series);

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

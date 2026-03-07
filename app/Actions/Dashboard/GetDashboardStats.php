<?php

declare(strict_types=1);

namespace App\Actions\Dashboard;

use App\Enums\ConversationStatus;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

class GetDashboardStats
{
    private const DEFAULT_TIMEZONE = 'America/Bogota';

    /** @var array<string, string> */
    private const ALLOWED_RANGES = [
        'horas'     => 'horas',
        'dias'      => 'dias',
        'semana'    => 'semana',
        'mes'       => 'mes',
        'trimestre' => 'trimestre',
        'semestre'  => 'semestre',
        'ano'       => 'ano',
    ];

    /**
     * @return array{
     *   stats: array{open_conversations: int, total_contacts: int, messages_today: int, avg_response_time: ?int},
     *   conversation_chart: array{range: string, ranges: list<array{key: string, label: string}>, series: list<array{bucket_start: string, label: string, conversations: int}>, total: int, timezone: string},
     *   recent_conversations: list<array{id: string, contact_name: string, last_message: string, last_message_at: ?string}>
     * }
     */
    public function handle(User $user, string $range): array
    {
        $timezone = $user->tenant?->timezone ?: self::DEFAULT_TIMEZONE;
        $range    = $this->sanitizeRange($range);

        [$startAtLocal, $endAtLocal, $bucket] = $this->resolveRangeWindow($range, $timezone);

        return [
            'stats'                => $this->computeStats($timezone),
            'conversation_chart'   => $this->buildChart($startAtLocal, $endAtLocal, $bucket, $range, $timezone),
            'recent_conversations' => $this->buildRecentConversations($timezone),
        ];
    }

    /** @return array{open_conversations: int, total_contacts: int, messages_today: int, avg_response_time: ?int} */
    private function computeStats(string $timezone): array
    {
        $openConversations = Conversation::query()
            ->whereIn('status', [ConversationStatus::Open->value, ConversationStatus::Pending->value])
            ->count();

        $totalContacts = Contact::query()->count();

        $todayStart    = CarbonImmutable::now($timezone)->startOfDay()->setTimezone('UTC');
        $todayEnd      = CarbonImmutable::now($timezone)->endOfDay()->setTimezone('UTC');
        $messagesToday = Message::query()->whereBetween('created_at', [$todayStart, $todayEnd])->count();

        $responseTimes = Conversation::query()
            ->whereNotNull('first_message_at')
            ->whereNotNull('first_response_at')
            ->get(['first_message_at', 'first_response_at'])
            ->map(fn (Conversation $c): int => (int) $c->first_message_at->diffInSeconds($c->first_response_at));

        $avgResponseTime = $responseTimes->isEmpty()
            ? null
            : (int) round((float) $responseTimes->avg());

        return [
            'open_conversations' => $openConversations,
            'total_contacts'     => $totalContacts,
            'messages_today'     => $messagesToday,
            'avg_response_time'  => $avgResponseTime,
        ];
    }

    /**
     * @return array{range: string, ranges: list<array{key: string, label: string}>, series: list<array{bucket_start: string, label: string, conversations: int}>, total: int, timezone: string}
     */
    private function buildChart(
        CarbonImmutable $startAt,
        CarbonImmutable $endAt,
        string $bucket,
        string $range,
        string $timezone,
    ): array {
        $series = $this->buildConversationSeries($startAt, $endAt, $bucket, $range, $timezone);

        return [
            'range'    => $range,
            'ranges'   => [
                ['key' => 'horas', 'label' => 'Horas'],
                ['key' => 'dias', 'label' => 'Días'],
                ['key' => 'semana', 'label' => 'Semana'],
                ['key' => 'mes', 'label' => 'Mes'],
                ['key' => 'trimestre', 'label' => 'Trimestre'],
                ['key' => 'semestre', 'label' => 'Semestre'],
                ['key' => 'ano', 'label' => 'Año'],
            ],
            'series'   => $series,
            'total'    => (int) array_sum(array_column($series, 'conversations')),
            'timezone' => $timezone,
        ];
    }

    /**
     * @return list<array{bucket_start: string, label: string, conversations: int}>
     */
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
            ->whereBetween('created_at', [
                $startAt->setTimezone('UTC'),
                $endAt->setTimezone('UTC'),
            ])
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

    /**
     * @return list<array{id: string, contact_name: string, last_message: string, last_message_at: ?string}>
     */
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
                    'id'             => $conversation->id,
                    'contact_name'   => $conversation->contact?->displayName() ?? 'Sin nombre',
                    'last_message'   => Str::limit($lastMessage?->body ?: 'Sin contenido', 80),
                    'last_message_at' => $lastMessage?->created_at?->setTimezone($timezone)?->toIso8601String(),
                ];
            })
            ->values()
            ->all();
    }

    private function sanitizeRange(string $range): string
    {
        return array_key_exists($range, self::ALLOWED_RANGES) ? $range : 'horas';
    }

    /** @return array{CarbonImmutable, CarbonImmutable, string} */
    private function resolveRangeWindow(string $range, string $timezone): array
    {
        $now = CarbonImmutable::now($timezone);

        return match ($range) {
            'horas'     => [$now->startOfDay(), $now->endOfDay(), 'hour'],
            'dias'      => [$now->subDays(29)->startOfDay(), $now->endOfDay(), 'day'],
            'semana'    => [$now->subDays(6)->startOfDay(), $now->endOfDay(), 'day'],
            'mes'       => [$now->startOfMonth(), $now->endOfDay(), 'day'],
            'trimestre' => [$now->subMonthsNoOverflow(2)->startOfMonth(), $now->endOfDay(), 'week'],
            'semestre'  => [$now->subMonthsNoOverflow(5)->startOfMonth(), $now->endOfDay(), 'month'],
            'ano'       => [$now->subMonthsNoOverflow(11)->startOfMonth(), $now->endOfDay(), 'month'],
            default     => [$now->subDays(6)->startOfDay(), $now->endOfDay(), 'day'],
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

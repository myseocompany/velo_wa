<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ConversationStatus;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    private const DEFAULT_RANGE = 'horas';
    private const CHART_TIMEZONE = 'America/Bogota';

    /** @var array<int, string> */
    private const ALLOWED_RANGES = [
        'horas',
        'dias',
        'semana',
        'mes',
        'trimestre',
        'semestre',
        'ano',
    ];

    public function __invoke(Request $request): Response
    {
        $timezone = $request->user()?->tenant?->timezone ?: self::CHART_TIMEZONE;
        $range = $this->sanitizeRange($request->string('range', self::DEFAULT_RANGE)->toString());
        [$startAtLocal, $endAtLocal, $bucket] = $this->resolveRangeWindow($range, $timezone);

        $openConversations = Conversation::query()
            ->whereIn('status', [ConversationStatus::Open->value, ConversationStatus::Pending->value])
            ->count();

        $totalContacts = Contact::query()->count();

        $todayStartUtc = CarbonImmutable::now($timezone)->startOfDay()->setTimezone('UTC');
        $todayEndUtc = CarbonImmutable::now($timezone)->endOfDay()->setTimezone('UTC');

        $messagesToday = Message::query()
            ->whereBetween('created_at', [$todayStartUtc, $todayEndUtc])
            ->count();

        $responseTimes = Conversation::query()
            ->whereNotNull('first_message_at')
            ->whereNotNull('first_response_at')
            ->get(['first_message_at', 'first_response_at'])
            ->map(fn (Conversation $conversation): int => (int) round((float) $conversation->first_message_at->diffInSeconds($conversation->first_response_at)));

        $avgResponseTime = $responseTimes->isEmpty()
            ? null
            : (int) round((float) $responseTimes->avg());

        $conversationSeries = $this->buildConversationSeries(
            $startAtLocal,
            $endAtLocal,
            $bucket,
            $range,
            $timezone
        );
        $recentConversations = $this->buildRecentConversations($timezone);

        Log::info('Dashboard stats computed', [
            'user_id' => $request->user()?->id,
            'tenant_id' => $request->user()?->tenant_id,
            'open_conversations' => $openConversations,
            'total_contacts' => $totalContacts,
            'messages_today' => $messagesToday,
            'avg_response_time' => $avgResponseTime,
            'chart_range' => $range,
            'chart_timezone' => $timezone,
            'chart_points' => count($conversationSeries),
            'recent_conversations' => count($recentConversations),
        ]);

        return Inertia::render('Dashboard', [
            'stats' => [
                'open_conversations' => $openConversations,
                'total_contacts' => $totalContacts,
                'messages_today' => $messagesToday,
                'avg_response_time' => $avgResponseTime,
            ],
            'conversation_chart' => [
                'range' => $range,
                'ranges' => [
                    ['key' => 'horas', 'label' => 'Horas'],
                    ['key' => 'dias', 'label' => 'Dias'],
                    ['key' => 'semana', 'label' => 'Semana'],
                    ['key' => 'mes', 'label' => 'Mes'],
                    ['key' => 'trimestre', 'label' => 'Trimestre'],
                    ['key' => 'semestre', 'label' => 'Semestre'],
                    ['key' => 'ano', 'label' => 'año'],
                ],
                'series' => $conversationSeries,
                'total' => array_sum(array_column($conversationSeries, 'conversations')),
                'timezone' => $timezone,
            ],
            'recent_conversations' => $recentConversations,
        ]);
    }

    private function sanitizeRange(string $range): string
    {
        return in_array($range, self::ALLOWED_RANGES, true) ? $range : self::DEFAULT_RANGE;
    }

    /** @return array{CarbonImmutable, CarbonImmutable, string} */
    private function resolveRangeWindow(string $range, string $timezone): array
    {
        $now = CarbonImmutable::now($timezone);

        return match ($range) {
            'horas' => [$now->startOfDay(), $now->endOfDay(), 'hour'],
            'dias' => [$now->subDays(29)->startOfDay(), $now->endOfDay(), 'day'],
            'semana' => [$now->subDays(6)->startOfDay(), $now->endOfDay(), 'day'],
            'mes' => [$now->startOfMonth(), $now->endOfDay(), 'day'],
            'trimestre' => [$now->subMonthsNoOverflow(2)->startOfMonth(), $now->endOfDay(), 'week'],
            'semestre' => [$now->subMonthsNoOverflow(5)->startOfMonth(), $now->endOfDay(), 'month'],
            'ano' => [$now->subMonthsNoOverflow(11)->startOfMonth(), $now->endOfDay(), 'month'],
            default => [$now->subDays(6)->startOfDay(), $now->endOfDay(), 'day'],
        };
    }

    /**
     * @return array<int, array{bucket_start:string,label:string,conversations:int}>
     */
    private function buildConversationSeries(
        CarbonImmutable $startAt,
        CarbonImmutable $endAt,
        string $bucket,
        string $range,
        string $timezone
    ): array {
        $series = [];
        $cursor = $this->truncateToBucket($startAt, $bucket);

        while ($cursor->lessThanOrEqualTo($endAt)) {
            $key = $cursor->format('Y-m-d H:i:s');
            $series[$key] = [
                'bucket_start' => $cursor->toIso8601String(),
                'label' => $this->formatLabel($cursor, $bucket, $range),
                'conversations' => 0,
            ];

            $cursor = $this->addBucket($cursor, $bucket);
        }

        $startUtc = $startAt->setTimezone('UTC');
        $endUtc = $endAt->setTimezone('UTC');

        $conversations = Conversation::query()
            ->whereBetween('created_at', [$startUtc, $endUtc])
            ->get(['created_at']);

        foreach ($conversations as $conversation) {
            if (! $conversation->created_at) {
                continue;
            }

            $bucketStart = $this->truncateToBucket(
                CarbonImmutable::instance($conversation->created_at)->setTimezone($timezone),
                $bucket
            );

            $key = $bucketStart->format('Y-m-d H:i:s');
            if (! isset($series[$key])) {
                continue;
            }

            $series[$key]['conversations']++;
        }

        return array_values($series);
    }

    private function truncateToBucket(CarbonImmutable $value, string $bucket): CarbonImmutable
    {
        return match ($bucket) {
            'hour' => $value->startOfHour(),
            'day' => $value->startOfDay(),
            'week' => $value->startOfWeek(),
            'month' => $value->startOfMonth(),
            default => $value->startOfDay(),
        };
    }

    private function addBucket(CarbonImmutable $value, string $bucket): CarbonImmutable
    {
        return match ($bucket) {
            'hour' => $value->addHour(),
            'day' => $value->addDay(),
            'week' => $value->addWeek(),
            'month' => $value->addMonth(),
            default => $value->addDay(),
        };
    }

    private function formatLabel(CarbonImmutable $value, string $bucket, string $range): string
    {
        if ($bucket === 'hour') {
            return $value->format('H:i');
        }

        if ($bucket === 'week') {
            return $value->format('d/m');
        }

        if ($bucket === 'month') {
            return $range === 'ano'
                ? $value->format('m/Y')
                : $value->format('M Y');
        }

        return $value->format('d/m');
    }

    /**
     * @return array<int, array{id:string,contact_name:string,last_message:string,last_message_at:?string}>
     */
    private function buildRecentConversations(string $timezone): array
    {
        return Conversation::query()
            ->whereHas('messages')
            ->with([
                'contact:id,name,push_name,phone',
                'messages' => fn ($query) => $query->reorder()->orderByDesc('created_at')->limit(1),
            ])
            ->orderByDesc('last_message_at')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(function (Conversation $conversation) use ($timezone): array {
                $lastMessage = $conversation->messages->first();
                $contactName = $conversation->contact?->displayName() ?? 'Sin nombre';

                return [
                    'id' => $conversation->id,
                    'contact_name' => $contactName,
                    'last_message' => Str::limit($lastMessage?->body ?: 'Sin contenido', 80),
                    'last_message_at' => $lastMessage?->created_at?->setTimezone($timezone)?->toIso8601String(),
                ];
            })
            ->values()
            ->all();
    }
}

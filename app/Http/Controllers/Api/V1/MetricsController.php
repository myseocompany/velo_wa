<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\ConversationStatus;
use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class MetricsController extends Controller
{
    private const DEFAULT_TIMEZONE = 'America/Bogota';

    /**
     * GET /api/v1/metrics/agents?range=semana
     * Agent performance table: Dt1 avg, conversations handled, messages sent.
     */
    public function agents(Request $request): JsonResponse
    {
        $timezone = $request->user()->tenant?->timezone ?: self::DEFAULT_TIMEZONE;
        $range    = $this->sanitizeRange($request->string('range', 'semana')->toString());

        [$startUtc, $endUtc] = $this->resolveRange($range, $timezone);

        $rows = DB::table('users as u')
            ->select([
                'u.id',
                'u.name',
                DB::raw('COUNT(DISTINCT c.id) AS conversations_handled'),
                DB::raw('ROUND(AVG(EXTRACT(EPOCH FROM (c.first_response_at - c.first_message_at))))::int AS avg_dt1'),
                DB::raw('COUNT(DISTINCT m.id) AS messages_sent'),
            ])
            ->leftJoin('conversations as c', function ($join) use ($startUtc, $endUtc) {
                $join->on('c.assigned_to', '=', 'u.id')
                    ->whereBetween('c.created_at', [$startUtc, $endUtc])
                    ->whereNotNull('c.first_response_at');
            })
            ->leftJoin('messages as m', function ($join) use ($startUtc, $endUtc) {
                $join->on('m.sent_by', '=', 'u.id')
                    ->where('m.direction', 'out')
                    ->whereBetween('m.created_at', [$startUtc, $endUtc]);
            })
            ->where('u.tenant_id', $request->user()->tenant_id)
            ->where('u.is_active', true)
            ->groupBy('u.id', 'u.name')
            ->orderByDesc('conversations_handled')
            ->get();

        return response()->json([
            'data' => $rows->map(fn ($row) => [
                'id'                     => $row->id,
                'name'                   => $row->name,
                'conversations_handled'  => (int) $row->conversations_handled,
                'avg_dt1'                => $row->avg_dt1 !== null ? (int) $row->avg_dt1 : null,
                'messages_sent'          => (int) $row->messages_sent,
            ])->values(),
            'range' => $range,
        ]);
    }

    /**
     * GET /api/v1/metrics/export?range=semana&type=conversations
     * Download a CSV with metrics for the selected range.
     */
    public function export(Request $request): Response
    {
        $timezone = $request->user()->tenant?->timezone ?: self::DEFAULT_TIMEZONE;
        $range    = $this->sanitizeRange($request->string('range', 'semana')->toString());
        $type     = $request->string('type', 'conversations')->toString();

        [$startUtc, $endUtc] = $this->resolveRange($range, $timezone);

        return match ($type) {
            'agents'  => $this->exportAgents($request, $startUtc, $endUtc, $range, $timezone),
            default   => $this->exportConversations($startUtc, $endUtc, $range, $timezone),
        };
    }

    private function exportConversations(
        CarbonImmutable $startUtc,
        CarbonImmutable $endUtc,
        string $range,
        string $timezone,
    ): Response {
        $conversations = Conversation::query()
            ->with(['contact:id,name,push_name,phone', 'assignee:id,name'])
            ->whereBetween('created_at', [$startUtc, $endUtc])
            ->orderByDesc('created_at')
            ->get();

        $headers = ['ID', 'Contacto', 'Teléfono', 'Estado', 'Agente', 'Mensajes', 'Dt1 (seg)', 'Creada', 'Cerrada'];

        $rows = $conversations->map(fn (Conversation $c) => [
            $c->id,
            $c->contact?->displayName() ?? '',
            $c->contact?->phone ?? '',
            $c->status->value,
            $c->assignee?->name ?? 'Sin asignar',
            $c->message_count,
            $c->dt1() ?? '',
            $c->created_at->setTimezone($timezone)->format('Y-m-d H:i'),
            $c->closed_at?->setTimezone($timezone)->format('Y-m-d H:i') ?? '',
        ]);

        return $this->csvResponse("conversaciones_{$range}.csv", $headers, $rows->toArray());
    }

    private function exportAgents(
        Request $request,
        CarbonImmutable $startUtc,
        CarbonImmutable $endUtc,
        string $range,
        string $timezone,
    ): Response {
        $rows = DB::table('users as u')
            ->select([
                'u.name',
                DB::raw('COUNT(DISTINCT c.id) AS conversations_handled'),
                DB::raw('ROUND(AVG(EXTRACT(EPOCH FROM (c.first_response_at - c.first_message_at))))::int AS avg_dt1'),
                DB::raw('COUNT(DISTINCT m.id) AS messages_sent'),
            ])
            ->leftJoin('conversations as c', function ($join) use ($startUtc, $endUtc) {
                $join->on('c.assigned_to', '=', 'u.id')
                    ->whereBetween('c.created_at', [$startUtc, $endUtc])
                    ->whereNotNull('c.first_response_at');
            })
            ->leftJoin('messages as m', function ($join) use ($startUtc, $endUtc) {
                $join->on('m.sent_by', '=', 'u.id')
                    ->where('m.direction', 'out')
                    ->whereBetween('m.created_at', [$startUtc, $endUtc]);
            })
            ->where('u.tenant_id', $request->user()->tenant_id)
            ->where('u.is_active', true)
            ->groupBy('u.id', 'u.name')
            ->orderByDesc('conversations_handled')
            ->get();

        $headers = ['Agente', 'Conversaciones', 'Dt1 promedio (seg)', 'Mensajes enviados'];
        $data    = $rows->map(fn ($r) => [$r->name, $r->conversations_handled, $r->avg_dt1 ?? '', $r->messages_sent])->toArray();

        return $this->csvResponse("agentes_{$range}.csv", $headers, $data);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function sanitizeRange(string $range): string
    {
        return in_array($range, ['horas', 'semana', 'mes', 'trimestre', 'ano'], true) ? $range : 'semana';
    }

    /** @return array{CarbonImmutable, CarbonImmutable} */
    private function resolveRange(string $range, string $timezone): array
    {
        $now = CarbonImmutable::now($timezone);

        [$start, $end] = match ($range) {
            'horas'     => [$now->startOfDay(),                            $now->endOfDay()],
            'semana'    => [$now->subDays(6)->startOfDay(),                $now->endOfDay()],
            'mes'       => [$now->startOfMonth(),                          $now->endOfDay()],
            'trimestre' => [$now->subMonthsNoOverflow(2)->startOfMonth(),  $now->endOfDay()],
            'ano'       => [$now->subMonthsNoOverflow(11)->startOfMonth(), $now->endOfDay()],
            default     => [$now->subDays(6)->startOfDay(),                $now->endOfDay()],
        };

        return [$start->setTimezone('UTC'), $end->setTimezone('UTC')];
    }

    /** @param list<string> $headers  @param list<list<mixed>> $rows */
    private function csvResponse(string $filename, array $headers, array $rows): Response
    {
        $buffer = fopen('php://temp', 'r+');
        fputcsv($buffer, $headers);
        foreach ($rows as $row) {
            fputcsv($buffer, $row);
        }
        rewind($buffer);
        $csv = stream_get_contents($buffer);
        fclose($buffer);

        return response($csv, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}

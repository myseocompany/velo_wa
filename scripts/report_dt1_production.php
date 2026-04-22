<?php

/**
 * DT1 Production Report
 *
 * Queries real conversations and shows the algorithm breakdown:
 * - First contact message
 * - First human agent response (used for DT1)
 * - Projected start_lab and end_lab times
 * - Stored vs recalculated dt1_minutes_business
 *
 * Filters:
 * - First conversation of each contact only
 * - Excludes contacts tagged with exclude_from_metrics
 * - Only conversations with dt1_minutes_business already calculated
 *
 * Usage: php scripts/report_dt1_production.php [tenant_id] > report.csv
 *        php scripts/report_dt1_production.php               → all tenants
 */

declare(strict_types=1);

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

$filterTenantId = $argv[1] ?? null;

// ── Algorithm (mirrors CalculateDt1.php) ────────────────────────────────────

function getDayBlocks(CarbonImmutable $t, array $bh): array
{
    $dow = strtolower($t->format('l'));
    $day = $bh[$dow] ?? null;

    if (! $day || empty($day['enabled']) || empty($day['blocks'])) {
        return [];
    }

    $blocks = $day['blocks'];
    usort($blocks, static fn ($a, $b) => strcmp($a['start'], $b['start']));

    return $blocks;
}

function nextBusinessOpen(CarbonImmutable $from, array $bh): CarbonImmutable
{
    $cursor = $from->startOfDay();
    for ($i = 0; $i < 7; $i++) {
        $blocks = getDayBlocks($cursor, $bh);
        if (! empty($blocks)) {
            return $cursor->setTimeFromTimeString($blocks[0]['start']);
        }
        $cursor = $cursor->addDay()->startOfDay();
    }
    return $from->addDay();
}

function projectStart(CarbonImmutable $t, array $bh): CarbonImmutable
{
    $blocks = getDayBlocks($t, $bh);
    if (empty($blocks)) {
        return nextBusinessOpen($t->addDay()->startOfDay(), $bh);
    }
    foreach ($blocks as $block) {
        $open  = $t->setTimeFromTimeString($block['start']);
        $close = $t->setTimeFromTimeString($block['end']);
        if ($t->lessThan($open))           return $open;
        if ($t->lessThanOrEqualTo($close)) return $t;
    }
    return nextBusinessOpen($t->addDay()->startOfDay(), $bh);
}

function projectEnd(CarbonImmutable $t, array $bh): CarbonImmutable
{
    $blocks = getDayBlocks($t, $bh);
    if (empty($blocks)) {
        return nextBusinessOpen($t->startOfDay(), $bh);
    }
    $prevClose = null;
    foreach ($blocks as $block) {
        $open  = $t->setTimeFromTimeString($block['start']);
        $close = $t->setTimeFromTimeString($block['end']);
        if ($t->lessThan($open))           return $prevClose ?? $open;
        if ($t->lessThanOrEqualTo($close)) return $t;
        $prevClose = $close;
    }
    return $prevClose;
}

function minutesInDayRange(CarbonImmutable $from, CarbonImmutable $to, array $bh): int
{
    $blocks = getDayBlocks($from, $bh);
    $total  = 0;
    foreach ($blocks as $block) {
        $blockOpen  = $from->setTimeFromTimeString($block['start']);
        $blockClose = $from->setTimeFromTimeString($block['end']);
        $s = $from->isAfter($blockOpen)  ? $from : $blockOpen;
        $e = $to->isBefore($blockClose)  ? $to   : $blockClose;
        if ($e->isAfter($s)) $total += (int) $s->diffInMinutes($e);
    }
    return max(0, $total);
}

function minutesInFullDay(CarbonImmutable $date, array $bh): int
{
    $total = 0;
    foreach (getDayBlocks($date, $bh) as $block) {
        [$oh, $om] = array_map('intval', explode(':', $block['start']));
        [$ch, $cm] = array_map('intval', explode(':', $block['end']));
        $total += ($ch * 60 + $cm) - ($oh * 60 + $om);
    }
    return max(0, $total);
}

function workingMinutes(CarbonImmutable $startLab, CarbonImmutable $endLab, array $bh): int
{
    if ($startLab->isSameDay($endLab)) {
        return minutesInDayRange($startLab, $endLab, $bh);
    }
    $minutes  = minutesInDayRange($startLab, $startLab->endOfDay(), $bh);
    $cursor   = $startLab->addDay()->startOfDay();
    while ($cursor->isBefore($endLab->startOfDay())) {
        $minutes += minutesInFullDay($cursor, $bh);
        $cursor   = $cursor->addDay()->startOfDay();
    }
    $minutes += minutesInDayRange($endLab->startOfDay(), $endLab, $bh);
    return max(0, $minutes);
}

function calcDt1(CarbonImmutable $tIn, CarbonImmutable $tOut, array $bh): array
{
    $startLab = projectStart($tIn,  $bh);
    $endLab   = projectEnd($tOut, $bh);
    $minutes  = $endLab->isAfter($startLab) ? workingMinutes($startLab, $endLab, $bh) : 0;
    return compact('startLab', 'endLab', 'minutes');
}

function normalizeBusinessHours(?array $bh): array
{
    $default = [];
    foreach (['monday','tuesday','wednesday','thursday','friday'] as $d) {
        $default[$d] = ['enabled' => true, 'blocks' => [['start' => '08:00', 'end' => '18:00']]];
    }
    foreach (['saturday','sunday'] as $d) {
        $default[$d] = ['enabled' => false, 'blocks' => [['start' => '08:00', 'end' => '18:00']]];
    }

    if (empty($bh)) return $default;

    // Backwards-compat: old {start, end} format
    $first = reset($bh);
    if (isset($first['start'])) {
        return array_map(static fn ($day) => [
            'enabled' => $day['enabled'] ?? true,
            'blocks'  => [['start' => $day['start'], 'end' => $day['end']]],
        ], $bh);
    }

    return $bh;
}

// ── Load tenants ─────────────────────────────────────────────────────────────

$tenants = Tenant::query()
    ->when($filterTenantId, fn ($q) => $q->where('id', $filterTenantId))
    ->get()
    ->keyBy('id');

// ── Find first conversations (one per contact, with dt1 calculated) ──────────

$excludedContactIds = DB::table('contact_tag')
    ->join('tags', 'tags.id', '=', 'contact_tag.tag_id')
    ->where('tags.exclude_from_metrics', true)
    ->when($filterTenantId, fn ($q) => $q->where('tags.tenant_id', $filterTenantId))
    ->pluck('contact_tag.contact_id')
    ->unique()
    ->values();

// First conversation per contact = MIN(created_at) grouped by contact_id
$firstConvIds = DB::table('conversations')
    ->select(DB::raw('MIN(id) as id'))
    ->when($filterTenantId, fn ($q) => $q->where('tenant_id', $filterTenantId))
    ->whereNotIn('contact_id', $excludedContactIds)
    ->whereNotNull('dt1_minutes_business')
    ->whereNotNull('first_human_response_at')
    ->groupBy('contact_id')
    ->pluck('id');

$conversations = Conversation::query()
    ->with(['contact:id,phone,push_name,name', 'tenant:id,timezone,business_hours'])
    ->whereIn('id', $firstConvIds)
    ->orderBy('first_message_at')
    ->get();

// ── CSV output ────────────────────────────────────────────────────────────────

$out = fopen('php://stdout', 'w');

fputcsv($out, [
    'tenant_id',
    'conversation_id',
    'phone',
    'primera_entrada',
    'primer_msg_in',
    'primera_salida_humana',
    'primer_msg_out',
    'start_lab',
    'end_lab',
    'dt1_guardado_min',
    'dt1_recalculado_min',
    'match',
]);

$pass = 0;
$fail = 0;

foreach ($conversations as $conv) {
    $tenant   = $conv->tenant;
    $timezone = $tenant?->timezone ?: 'America/Bogota';
    $bh       = normalizeBusinessHours($tenant?->business_hours);

    $tIn  = CarbonImmutable::parse($conv->first_message_at)->setTimezone($timezone);
    $tOut = CarbonImmutable::parse($conv->first_human_response_at)->setTimezone($timezone);

    $calc = calcDt1($tIn, $tOut, $bh);

    // Get first inbound message body
    $msgIn = Message::query()
        ->where('conversation_id', $conv->id)
        ->where('direction', 'in')
        ->orderBy('created_at')
        ->value('body');

    // Get first human outbound message body
    $msgOut = Message::query()
        ->where('conversation_id', $conv->id)
        ->where('direction', 'out')
        ->where('is_automated', false)
        ->whereNotExists(function ($q) use ($conv) {
            $q->select(DB::raw(1))
                ->from('quick_replies')
                ->where('quick_replies.tenant_id', $conv->tenant_id)
                ->where('quick_replies.is_auto_reply', true)
                ->whereRaw('LOWER(TRIM(quick_replies.body)) = LOWER(TRIM(messages.body))');
        })
        ->orderBy('created_at')
        ->value('body');

    $stored      = (int) $conv->dt1_minutes_business;
    $recalc      = $calc['minutes'];
    $match       = abs($stored - $recalc) < 1 ? 'OK' : 'DIFF';

    if ($match === 'OK') $pass++; else $fail++;

    fputcsv($out, [
        $conv->tenant_id,
        $conv->id,
        $conv->contact?->phone ?? '—',
        $tIn->format('d/m/Y H:i:s'),
        mb_strimwidth($msgIn ?? '', 0, 80, '…'),
        $tOut->format('d/m/Y H:i:s'),
        mb_strimwidth($msgOut ?? '', 0, 80, '…'),
        $calc['startLab']->format('d/m/Y H:i:s'),
        $calc['endLab']->format('d/m/Y H:i:s'),
        $stored,
        $recalc,
        $match,
    ]);
}

fclose($out);

// ── Summary to stderr ─────────────────────────────────────────────────────────

$total = $pass + $fail;
fwrite(STDERR, "\n── DT1 Production Report ──────────────────────────────────\n");
fwrite(STDERR, "  Total conversaciones: {$total}\n");
fwrite(STDERR, "  Match (OK):           {$pass}\n");
fwrite(STDERR, "  Diferencia (DIFF):    {$fail}\n");
fwrite(STDERR, "──────────────────────────────────────────────────────────\n\n");

<?php

/**
 * Test CalculateDt1 algorithm against AMIA ginecología CSV data.
 * Standalone — no Laravel bootstrap needed.
 *
 * Usage: php scripts/test_dt1_csv.php
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Carbon\CarbonImmutable;

// ── Algorithm (mirrors CalculateDt1.php exactly) ────────────────────────────

function getDayBlocks(CarbonImmutable $t, array $businessHours): array
{
    $dow = strtolower($t->format('l')); // 'monday', 'tuesday', …
    $day = $businessHours[$dow] ?? null;

    if (! $day || empty($day['enabled']) || empty($day['blocks'])) {
        return [];
    }

    $blocks = $day['blocks'];
    usort($blocks, static fn (array $a, array $b): int => strcmp($a['start'], $b['start']));

    return $blocks;
}

function projectStart(CarbonImmutable $t, array $businessHours): CarbonImmutable
{
    $blocks = getDayBlocks($t, $businessHours);

    if (empty($blocks)) {
        return nextBusinessOpen($t->addDay()->startOfDay(), $businessHours);
    }

    foreach ($blocks as $block) {
        $open  = $t->setTimeFromTimeString($block['start']);
        $close = $t->setTimeFromTimeString($block['end']);

        if ($t->lessThan($open)) {
            return $open;
        }
        if ($t->lessThanOrEqualTo($close)) {
            return $t;
        }
    }

    return nextBusinessOpen($t->addDay()->startOfDay(), $businessHours);
}

function projectEnd(CarbonImmutable $t, array $businessHours): CarbonImmutable
{
    $blocks = getDayBlocks($t, $businessHours);

    if (empty($blocks)) {
        return nextBusinessOpen($t->startOfDay(), $businessHours);
    }

    $prevClose = null;

    foreach ($blocks as $block) {
        $open  = $t->setTimeFromTimeString($block['start']);
        $close = $t->setTimeFromTimeString($block['end']);

        if ($t->lessThan($open)) {
            return $prevClose ?? $open;
        }
        if ($t->lessThanOrEqualTo($close)) {
            return $t;
        }

        $prevClose = $close;
    }

    return $prevClose;
}

function nextBusinessOpen(CarbonImmutable $from, array $businessHours): CarbonImmutable
{
    $cursor = $from->startOfDay();

    for ($i = 0; $i < 7; $i++) {
        $blocks = getDayBlocks($cursor, $businessHours);

        if (! empty($blocks)) {
            return $cursor->setTimeFromTimeString($blocks[0]['start']);
        }

        $cursor = $cursor->addDay()->startOfDay();
    }

    return $from->addDay();
}

function minutesInDayRange(CarbonImmutable $from, CarbonImmutable $to, array $businessHours): int
{
    $blocks = getDayBlocks($from, $businessHours);
    $total  = 0;

    foreach ($blocks as $block) {
        $blockOpen  = $from->setTimeFromTimeString($block['start']);
        $blockClose = $from->setTimeFromTimeString($block['end']);

        $overlapStart = $from->isAfter($blockOpen) ? $from : $blockOpen;
        $overlapEnd   = $to->isBefore($blockClose) ? $to : $blockClose;

        if ($overlapEnd->isAfter($overlapStart)) {
            $total += (int) $overlapStart->diffInMinutes($overlapEnd);
        }
    }

    return max(0, $total);
}

function minutesInFullDay(CarbonImmutable $date, array $businessHours): int
{
    $blocks = getDayBlocks($date, $businessHours);
    $total  = 0;

    foreach ($blocks as $block) {
        [$oh, $om] = array_map('intval', explode(':', $block['start']));
        [$ch, $cm] = array_map('intval', explode(':', $block['end']));
        $total += ($ch * 60 + $cm) - ($oh * 60 + $om);
    }

    return max(0, $total);
}

function workingMinutes(CarbonImmutable $startLab, CarbonImmutable $endLab, array $businessHours): int
{
    if ($startLab->isSameDay($endLab)) {
        return minutesInDayRange($startLab, $endLab, $businessHours);
    }

    $minutes = 0;
    $minutes += minutesInDayRange($startLab, $startLab->endOfDay(), $businessHours);

    $cursor = $startLab->addDay()->startOfDay();
    while ($cursor->isBefore($endLab->startOfDay())) {
        $minutes += minutesInFullDay($cursor, $businessHours);
        $cursor   = $cursor->addDay()->startOfDay();
    }

    $minutes += minutesInDayRange($endLab->startOfDay(), $endLab, $businessHours);

    return max(0, $minutes);
}

function businessMinutes(CarbonImmutable $tIn, CarbonImmutable $tOut, array $bh): int
{
    $startLab = projectStart($tIn, $bh);
    $endLab   = projectEnd($tOut, $bh);

    if (! $endLab->isAfter($startLab)) {
        return 0;
    }

    return workingMinutes($startLab, $endLab, $bh);
}

// ── Config ───────────────────────────────────────────────────────────────────

$timezone = 'America/Bogota';

$businessHours = [
    'monday'    => ['enabled' => true,  'blocks' => [['start' => '08:00', 'end' => '18:00']]],
    'tuesday'   => ['enabled' => true,  'blocks' => [['start' => '08:00', 'end' => '18:00']]],
    'wednesday' => ['enabled' => true,  'blocks' => [['start' => '08:00', 'end' => '18:00']]],
    'thursday'  => ['enabled' => true,  'blocks' => [['start' => '08:00', 'end' => '18:00']]],
    'friday'    => ['enabled' => true,  'blocks' => [['start' => '08:00', 'end' => '18:00']]],
    'saturday'  => ['enabled' => false, 'blocks' => [['start' => '08:00', 'end' => '18:00']]],
    'sunday'    => ['enabled' => false, 'blocks' => [['start' => '08:00', 'end' => '18:00']]],
];

// ── Parse CSV ────────────────────────────────────────────────────────────────

$csvPath = __DIR__ . '/../docs/AMIA__ginecologia_conversations_FRT.csv';
$handle  = fopen($csvPath, 'r');

if ($handle === false) {
    echo "ERROR: Cannot open CSV\n";
    exit(1);
}

$rawHeader = fgetcsv($handle);
$header    = array_map(fn ($h) => trim(str_replace("\xEF\xBB\xBF", '', $h)), $rawHeader);

$rows = [];
while (($row = fgetcsv($handle)) !== false) {
    if (count($row) === count($header)) {
        $rows[] = array_combine($header, $row);
    }
}
fclose($handle);

// ── Group by phone ───────────────────────────────────────────────────────────

$grouped = [];
foreach ($rows as $row) {
    $phone = $row['phone'];
    $dir   = strtolower(trim($row['direction']));

    if ($dir === 'in') {
        $grouped[$phone]['in']  = $row;
    } elseif ($dir === 'out') {
        $grouped[$phone]['out'] = $row;
    }
}

// ── Run comparisons ──────────────────────────────────────────────────────────

$pass     = 0;
$fail     = 0;
$skipped  = 0;
$failures = [];

foreach ($grouped as $phone => $pair) {
    if (! isset($pair['in'], $pair['out'])) {
        $skipped++;
        continue;
    }

    $inRow  = $pair['in'];
    $outRow = $pair['out'];

    $expectedStr = trim($outRow['tiempo_respuesta_horas_lab']);
    if ($expectedStr === '') {
        $skipped++;
        continue;
    }

    $expected = (float) str_replace(',', '.', $expectedStr);

    try {
        $tIn  = CarbonImmutable::createFromFormat('d/m/Y H:i:s', trim($inRow['created_at']),  $timezone);
        $tOut = CarbonImmutable::createFromFormat('d/m/Y H:i:s', trim($outRow['created_at']), $timezone);
    } catch (\Throwable $e) {
        echo "SKIP parse error for phone $phone: {$e->getMessage()}\n";
        $skipped++;
        continue;
    }

    $got = businessMinutes($tIn, $tOut, $businessHours);

    $diff = abs($got - $expected);
    $ok   = $diff < 1.0;

    if ($ok) {
        $pass++;
    } else {
        $fail++;
        $failures[] = compact('phone', 'inRow', 'outRow', 'expected', 'got', 'diff');
    }
}

// ── Report ────────────────────────────────────────────────────────────────────

$total = $pass + $fail;

echo "\n";
echo "══════════════════════════════════════════════════════════════════\n";
echo "  DT1 Algorithm Test — AMIA Ginecología CSV\n";
echo "══════════════════════════════════════════════════════════════════\n\n";
echo sprintf("  Pass:    %d / %d\n", $pass, $total);
echo sprintf("  Fail:    %d / %d\n", $fail, $total);
echo sprintf("  Skipped: %d\n\n", $skipped);

if (empty($failures)) {
    echo "  ✓  All cases PASS\n\n";
} else {
    echo "── Failures ───────────────────────────────────────────────────────\n\n";

    foreach ($failures as $f) {
        $exp = number_format($f['expected'], 2);

        echo sprintf(
            "  Phone: %s\n    in:       %s\n    out:      %s\n    expected: %s min\n    got:      %d min\n    diff:     %s min\n\n",
            $f['phone'],
            $f['inRow']['created_at'],
            $f['outRow']['created_at'],
            $exp, $f['got'],
            number_format($f['diff'], 2),
        );
    }
}

echo "══════════════════════════════════════════════════════════════════\n\n";

<?php

declare(strict_types=1);

namespace App\Services\AiAgent\Tools;

use App\Actions\Reservations\BuildReservationSlots;
use App\Models\BookableUnit;
use App\Models\Conversation;
use App\Models\Tenant;
use App\Support\AmiaServiceCatalog;
use Carbon\CarbonImmutable;

class GetAvailableSlotsTool implements Tool
{
    public function __construct(private readonly BuildReservationSlots $slots) {}

    public function name(): string
    {
        return 'get_available_slots';
    }

    public function description(): string
    {
        return 'Consulta disponibilidad real de agenda para un servicio.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'service' => ['type' => 'string'],
                'jornada' => ['type' => 'string', 'enum' => ['morning', 'afternoon']],
                'day' => ['type' => 'string'],
                'professional_unit_id' => ['type' => 'string'],
            ],
            'required' => ['service'],
        ];
    }

    public function execute(Conversation $conversation, array $input): array
    {
        $service = trim((string) ($input['service'] ?? ''));
        if ($service === '') {
            return ['error' => 'service_required'];
        }

        $tenant = Tenant::withoutGlobalScope('tenant')->find($conversation->tenant_id);
        if (! $tenant) {
            return ['error' => 'tenant_not_found'];
        }

        $timezone = $tenant->timezone ?: 'America/Bogota';
        $day = is_string($input['day'] ?? null) && trim($input['day']) !== ''
            ? CarbonImmutable::parse($input['day'], $timezone)->startOfDay()
            : CarbonImmutable::now($timezone)->startOfDay();
        $days = isset($input['day']) ? 3 : 7;
        $duration = AmiaServiceCatalog::durationFor($service);
        $jornada = in_array($input['jornada'] ?? null, ['morning', 'afternoon'], true) ? $input['jornada'] : null;

        $units = $this->resolveUnits($conversation, $service, $input['professional_unit_id'] ?? null);
        foreach ($units as $unit) {
            $available = array_slice($this->slots->handle($tenant, $day, $days, $duration, 30, $unit->id, $jornada), 0, 2);
            if ($available === []) {
                continue;
            }

            return [
                'slots' => array_map(static fn (array $slot): array => [
                    ...$slot,
                    'professional_name' => $unit->name,
                    'bookable_unit_id' => $unit->id,
                ], $available),
            ];
        }

        return ['slots' => []];
    }

    private function resolveUnits(Conversation $conversation, string $service, mixed $requestedUnitId): array
    {
        $query = BookableUnit::withoutGlobalScope('tenant')
            ->where('tenant_id', $conversation->tenant_id)
            ->where('type', 'professional')
            ->where('is_active', true)
            ->orderBy('name');

        if (is_string($requestedUnitId) && trim($requestedUnitId) !== '') {
            return $query->whereKey($requestedUnitId)->get()->all();
        }

        return $query->whereJsonContains('settings->services', $service)->get()->all();
    }
}

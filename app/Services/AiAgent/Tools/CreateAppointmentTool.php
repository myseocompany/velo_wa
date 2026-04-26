<?php

declare(strict_types=1);

namespace App\Services\AiAgent\Tools;

use App\Actions\Reservations\GenerateReservationCode;
use App\Enums\ReservationStatus;
use App\Models\BookableUnit;
use App\Models\Conversation;
use App\Models\Reservation;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class CreateAppointmentTool implements Tool
{
    public function __construct(private readonly GenerateReservationCode $generateCode) {}

    public function name(): string
    {
        return 'create_appointment';
    }

    public function description(): string
    {
        return 'Crea una reservacion/cita solicitada despues de confirmacion explicita de la paciente.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'starts_at' => ['type' => 'string'],
                'ends_at' => ['type' => 'string'],
                'service' => ['type' => 'string'],
                'bookable_unit_id' => ['type' => 'string'],
                'contact_name' => ['type' => 'string'],
                'contact_document' => ['type' => 'string'],
                'notes' => ['type' => 'string'],
            ],
            'required' => ['starts_at', 'ends_at', 'service', 'bookable_unit_id'],
        ];
    }

    public function execute(Conversation $conversation, array $input): array
    {
        try {
            return DB::transaction(function () use ($conversation, $input): array {
                $unit = BookableUnit::withoutGlobalScope('tenant')
                    ->where('tenant_id', $conversation->tenant_id)
                    ->where('is_active', true)
                    ->whereKey((string) ($input['bookable_unit_id'] ?? ''))
                    ->first();
                if (! $unit) {
                    return ['error' => 'bookable_unit_not_found'];
                }

                $startsAt = CarbonImmutable::parse((string) $input['starts_at'])->utc();
                $endsAt = CarbonImmutable::parse((string) $input['ends_at'])->utc();
                if ($endsAt->lessThanOrEqualTo($startsAt)) {
                    return ['error' => 'invalid_time_range'];
                }

                if (DB::connection()->getDriverName() === 'pgsql') {
                    DB::statement('SELECT pg_advisory_xact_lock(hashtext(?))', [
                        sprintf('%s|%s|%s', $conversation->tenant_id, $unit->id, $startsAt->toIso8601String()),
                    ]);
                }

                $occupied = Reservation::withoutGlobalScope('tenant')
                    ->where('tenant_id', $conversation->tenant_id)
                    ->where('bookable_unit_id', $unit->id)
                    ->whereNull('deleted_at')
                    ->whereNotIn('status', [ReservationStatus::Cancelled->value, ReservationStatus::NoShow->value])
                    ->where('starts_at', '<', $endsAt->toDateTimeString())
                    ->where('ends_at', '>', $startsAt->toDateTimeString())
                    ->exists();

                if ($occupied) {
                    return ['error' => 'slot_taken', 'message' => 'Ese horario ya no esta disponible'];
                }

                $contact = $conversation->contact;
                if ($contact && ! $contact->name && is_string($input['contact_name'] ?? null)) {
                    $contact->update(['name' => trim($input['contact_name'])]);
                }

                $notes = trim((string) ($input['notes'] ?? ''));
                if (is_string($input['contact_document'] ?? null) && trim($input['contact_document']) !== '') {
                    $notes = trim($notes . "\nDocumento: " . trim($input['contact_document']));
                }

                $reservation = Reservation::withoutGlobalScope('tenant')->create([
                    'tenant_id' => $conversation->tenant_id,
                    'contact_id' => $conversation->contact_id,
                    'conversation_id' => $conversation->id,
                    'assigned_to' => $conversation->assigned_to,
                    'bookable_unit_id' => $unit->id,
                    'service' => (string) $input['service'],
                    'code' => $this->generateCode->handle(),
                    'status' => ReservationStatus::Requested,
                    'starts_at' => $startsAt,
                    'ends_at' => $endsAt,
                    'party_size' => 1,
                    'notes' => $notes !== '' ? $notes : null,
                    'requested_at' => now(),
                ]);

                return [
                    'reservation_id' => $reservation->id,
                    'code' => $reservation->code,
                    'starts_at' => $reservation->starts_at->toIso8601String(),
                    'professional_name' => $unit->name,
                    'status' => ReservationStatus::Requested->value,
                ];
            });
        } catch (QueryException $exception) {
            if ($exception->getCode() === '23P01' || str_contains($exception->getMessage(), 'reservations_no_overlap')) {
                return ['error' => 'slot_taken', 'message' => 'Ese horario ya no esta disponible'];
            }

            throw $exception;
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Reservations\BuildReservationSlots;
use App\Actions\Reservations\GenerateReservationCode;
use App\Actions\Reservations\MoveReservationToStatus;
use App\Enums\ReservationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ReservationRequest;
use App\Http\Requests\Api\V1\UpdateReservationStatusRequest;
use App\Http\Resources\ReservationResource;
use App\Models\Reservation;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class ReservationController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Reservation::query()->with(['contact', 'assignee', 'bookableUnit']);

        $status = $request->string('status')->toString();
        if ($status !== '' && ReservationStatus::tryFrom($status) !== null) {
            $query->where('status', $status);
        }

        $contactId = $request->string('contact_id')->toString();
        if ($contactId !== '') {
            $query->where('contact_id', $contactId);
        }

        $conversationId = $request->string('conversation_id')->toString();
        if ($conversationId !== '') {
            $query->where('conversation_id', $conversationId);
        }

        $assignedTo = $request->string('assigned_to')->toString();
        if ($assignedTo === 'me') {
            $query->where('assigned_to', $request->user()->id);
        } elseif ($assignedTo === 'unassigned') {
            $query->whereNull('assigned_to');
        } elseif ($assignedTo !== '') {
            $query->where('assigned_to', $assignedTo);
        }

        $dateFrom = $request->string('date_from')->toString();
        if ($dateFrom !== '') {
            $query->whereDate('starts_at', '>=', $dateFrom);
        }
        $dateTo = $request->string('date_to')->toString();
        if ($dateTo !== '') {
            $query->whereDate('starts_at', '<=', $dateTo);
        }

        $driver = DB::connection()->getDriverName();
        $operator = $driver === 'pgsql' ? 'ilike' : 'like';
        $search = trim($request->string('search')->toString());
        if ($search !== '') {
            $query->where(function ($q) use ($operator, $search): void {
                $q->where('code', $operator, '%' . $search . '%')
                    ->orWhere('notes', $operator, '%' . $search . '%');
            });
        }

        $cases = collect(ReservationStatus::cases())
            ->map(fn (ReservationStatus $s, int $i) => "WHEN ? THEN {$i}")
            ->implode(' ');
        $bindings = array_column(ReservationStatus::cases(), 'value');

        $perPage = max(1, min((int) $request->integer('per_page', 100), 300));
        $reservations = $query
            ->orderByRaw("CASE status {$cases} ELSE 999 END", $bindings)
            ->orderBy('starts_at')
            ->paginate($perPage);

        return ReservationResource::collection($reservations);
    }

    public function slots(Request $request, BuildReservationSlots $action): JsonResponse
    {
        $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
            'days' => ['nullable', 'integer', 'min:1', 'max:14'],
            'duration_minutes' => ['nullable', 'integer', 'min:15', 'max:240'],
        ]);

        $tenant = $request->user()->tenant;
        $timezone = $tenant->timezone ?: 'America/Bogota';
        $date = $request->string('date')->toString();
        $days = max(1, min((int) $request->integer('days', 7), 14));
        $duration = max(15, min((int) $request->integer('duration_minutes', 60), 240));

        $base = $date !== ''
            ? CarbonImmutable::createFromFormat('Y-m-d', $date, $timezone)->startOfDay()
            : CarbonImmutable::now($timezone)->startOfDay();

        $slots = $action->handle($tenant, $base, $days, $duration);

        return response()->json(['data' => $slots]);
    }

    public function store(ReservationRequest $request, GenerateReservationCode $generateCode): JsonResponse
    {
        $status = ReservationStatus::tryFrom($request->input('status', ReservationStatus::Requested->value))
            ?? ReservationStatus::Requested;

        $reservation = Reservation::create([
            'tenant_id' => $request->user()->tenant_id,
            'contact_id' => $request->input('contact_id'),
            'conversation_id' => $request->input('conversation_id'),
            'assigned_to' => $request->input('assigned_to'),
            'bookable_unit_id' => $request->input('bookable_unit_id'),
            'service' => $request->input('service'),
            'code' => $generateCode->handle(),
            'status' => $status,
            'starts_at' => $request->date('starts_at'),
            'ends_at' => $request->date('ends_at'),
            'party_size' => $request->integer('party_size', 2),
            'notes' => $request->input('notes'),
            'requested_at' => now(),
        ]);

        return response()->json(['data' => new ReservationResource($reservation->load(['contact', 'assignee', 'bookableUnit']))], 201);
    }

    public function show(Reservation $reservation): JsonResponse
    {
        return response()->json([
            'data' => new ReservationResource($reservation->load(['contact', 'assignee', 'bookableUnit'])),
        ]);
    }

    public function update(ReservationRequest $request, Reservation $reservation): JsonResponse
    {
        $reservation->update([
            'contact_id' => $request->input('contact_id', $reservation->contact_id),
            'conversation_id' => $request->input('conversation_id', $reservation->conversation_id),
            'assigned_to' => $request->input('assigned_to', $reservation->assigned_to),
            'bookable_unit_id' => $request->input('bookable_unit_id', $reservation->bookable_unit_id),
            'service' => $request->input('service', $reservation->service),
            'starts_at' => $request->date('starts_at'),
            'ends_at' => $request->date('ends_at'),
            'party_size' => $request->integer('party_size', $reservation->party_size),
            'notes' => $request->input('notes'),
        ]);

        return response()->json([
            'data' => new ReservationResource($reservation->fresh(['contact', 'assignee', 'bookableUnit'])),
        ]);
    }

    public function updateStatus(
        UpdateReservationStatusRequest $request,
        Reservation $reservation,
        MoveReservationToStatus $action
    ): JsonResponse {
        $updated = $action->handle($reservation, $request->status());

        return response()->json([
            'data' => new ReservationResource($updated->load(['contact', 'assignee', 'bookableUnit'])),
        ]);
    }

    public function destroy(Reservation $reservation): Response
    {
        $reservation->delete();

        return response()->noContent();
    }

}

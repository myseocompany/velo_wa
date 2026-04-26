<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Actions\Reservations\BuildReservationSlots;
use App\Enums\ReservationStatus;
use App\Models\BookableUnit;
use App\Models\Contact;
use App\Models\Reservation;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BuildReservationSlotsTest extends TestCase
{
    use RefreshDatabase;

    public function test_multi_block_hours_and_unit_occupancy(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-04-27 07:00:00', 'America/Bogota'));

        $tenant = Tenant::create([
            'name' => 'Tenant Slots',
            'slug' => 'tenant-slots',
            'timezone' => 'America/Bogota',
            'business_hours' => [
                'monday' => ['enabled' => true, 'blocks' => [
                    ['start' => '08:00', 'end' => '12:00'],
                    ['start' => '14:00', 'end' => '18:00'],
                ]],
            ],
        ]);
        $contact = Contact::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'phone' => '573001112233',
        ]);
        $unitA = BookableUnit::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'type' => 'professional',
            'name' => 'A',
        ]);
        $unitB = BookableUnit::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'type' => 'professional',
            'name' => 'B',
        ]);

        Reservation::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'contact_id' => $contact->id,
            'bookable_unit_id' => $unitA->id,
            'code' => 'RES-UNITA',
            'status' => ReservationStatus::Requested,
            'starts_at' => CarbonImmutable::parse('2026-04-27 08:00:00', 'America/Bogota')->utc(),
            'ends_at' => CarbonImmutable::parse('2026-04-27 09:00:00', 'America/Bogota')->utc(),
        ]);

        $action = new BuildReservationSlots();
        $base = CarbonImmutable::parse('2026-04-27', 'America/Bogota');

        $unitASlots = $action->handle($tenant, $base, 1, 60, 60, $unitA->id);
        $unitBSlots = $action->handle($tenant, $base, 1, 60, 60, $unitB->id);
        $morningSlots = $action->handle($tenant, $base, 1, 60, 60, $unitB->id, 'morning');

        $this->assertNotContains('2026-04-27T13:00:00+00:00', array_column($unitASlots, 'starts_at'));
        $this->assertContains('2026-04-27T13:00:00+00:00', array_column($unitBSlots, 'starts_at'));
        $this->assertContains('2026-04-27T19:00:00+00:00', array_column($unitBSlots, 'starts_at'));
        $this->assertNotContains('2026-04-27T17:00:00+00:00', array_column($unitBSlots, 'starts_at'));
        $this->assertNotContains('2026-04-27T19:00:00+00:00', array_column($morningSlots, 'starts_at'));

        CarbonImmutable::setTestNow();
    }
}

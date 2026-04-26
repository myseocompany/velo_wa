<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Channel;
use App\Enums\ContactSource;
use App\Enums\ConversationStatus;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Reservation;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReservationApiTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $agent;

    private Contact $contact;

    private Conversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Tenant Reservations',
            'slug' => 'tenant-reservations',
            'timezone' => 'America/Bogota',
            'business_hours' => [
                'monday' => ['enabled' => true, 'start' => '09:00', 'end' => '18:00'],
                'tuesday' => ['enabled' => true, 'start' => '09:00', 'end' => '18:00'],
                'wednesday' => ['enabled' => true, 'start' => '09:00', 'end' => '18:00'],
                'thursday' => ['enabled' => true, 'start' => '09:00', 'end' => '18:00'],
                'friday' => ['enabled' => true, 'start' => '09:00', 'end' => '18:00'],
                'saturday' => ['enabled' => false, 'start' => '09:00', 'end' => '18:00'],
                'sunday' => ['enabled' => false, 'start' => '09:00', 'end' => '18:00'],
            ],
        ]);

        $this->agent = User::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->contact = Contact::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'phone' => '573004445566',
            'name' => 'Cliente Reserva',
            'source' => ContactSource::Manual,
        ]);

        $this->conversation = Conversation::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'contact_id' => $this->contact->id,
            'status' => ConversationStatus::Open,
            'channel' => Channel::WhatsApp,
        ]);
    }

    public function test_agent_can_list_slots_and_create_reservation(): void
    {
        $dateCursor = now('America/Bogota')->addDay();
        while (in_array($dateCursor->format('l'), ['Saturday', 'Sunday'], true)) {
            $dateCursor = $dateCursor->addDay();
        }
        $date = $dateCursor->format('Y-m-d');

        $slotsResponse = $this->actingAs($this->agent)->getJson("/api/v1/reservations/slots?date={$date}&days=1&duration_minutes=60");
        $slotsResponse->assertOk();
        $this->assertNotEmpty($slotsResponse->json('data'));

        $slot = $slotsResponse->json('data.0');

        $createResponse = $this->actingAs($this->agent)->postJson('/api/v1/reservations', [
            'contact_id' => $this->contact->id,
            'conversation_id' => $this->conversation->id,
            'starts_at' => $slot['starts_at'],
            'ends_at' => $slot['ends_at'],
            'party_size' => 4,
            'notes' => 'Mesa junto a ventana',
        ]);

        $createResponse->assertCreated();
        $createResponse->assertJsonPath('data.status', 'requested');
        $createResponse->assertJsonPath('data.party_size', 4);
        $createResponse->assertJsonMissingPath('data.tenant_id');
    }

    public function test_agent_can_change_reservation_status(): void
    {
        $start = now()->addDay()->startOfHour();
        $end = $start->copy()->addHour();

        $createResponse = $this->actingAs($this->agent)->postJson('/api/v1/reservations', [
            'contact_id' => $this->contact->id,
            'conversation_id' => $this->conversation->id,
            'starts_at' => $start->toIso8601String(),
            'ends_at' => $end->toIso8601String(),
            'party_size' => 2,
        ]);

        $id = $createResponse->json('data.id');

        $updateResponse = $this->actingAs($this->agent)->patchJson("/api/v1/reservations/{$id}/status", [
            'status' => 'confirmed',
        ]);

        $updateResponse->assertOk();
        $updateResponse->assertJsonPath('data.status', 'confirmed');
        $this->assertNotNull($updateResponse->json('data.confirmed_at'));
    }

    public function test_confirmed_timestamp_preserved_on_subsequent_status_change(): void
    {
        $start = now()->addDay()->startOfHour();
        $end = $start->copy()->addHour();

        $createResponse = $this->actingAs($this->agent)->postJson('/api/v1/reservations', [
            'contact_id' => $this->contact->id,
            'starts_at' => $start->toIso8601String(),
            'ends_at' => $end->toIso8601String(),
        ]);
        $id = $createResponse->json('data.id');

        $this->actingAs($this->agent)->patchJson("/api/v1/reservations/{$id}/status", ['status' => 'confirmed']);

        $response = $this->actingAs($this->agent)->patchJson("/api/v1/reservations/{$id}/status", ['status' => 'requested']);

        $response->assertOk();
        $response->assertJsonPath('data.status', 'requested');
        $this->assertNotNull($response->json('data.confirmed_at'), 'confirmed_at must be preserved after status change');
    }

    public function test_agent_cannot_access_other_tenant_reservation(): void
    {
        $otherTenant = Tenant::create(['name' => 'Otro Tenant', 'slug' => 'otro-tenant']);
        $otherContact = Contact::withoutGlobalScopes()->create([
            'tenant_id' => $otherTenant->id,
            'phone' => '573009998877',
            'name' => 'Contacto Otro',
            'source' => ContactSource::Manual,
        ]);
        $otherReservation = Reservation::withoutGlobalScopes()->create([
            'tenant_id' => $otherTenant->id,
            'contact_id' => $otherContact->id,
            'code' => 'RES-OTHER1',
            'status' => 'requested',
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHour(),
            'requested_at' => now(),
        ]);

        $response = $this->actingAs($this->agent)->getJson("/api/v1/reservations/{$otherReservation->id}");

        $response->assertNotFound();
    }

    public function test_agent_cannot_use_other_tenant_contact_in_reservation(): void
    {
        $otherTenant = Tenant::create(['name' => 'Otro Tenant 3', 'slug' => 'otro-tenant-3']);
        $otherContact = Contact::withoutGlobalScopes()->create([
            'tenant_id' => $otherTenant->id,
            'phone' => '573001112222',
            'name' => 'Contacto Externo',
            'source' => ContactSource::Manual,
        ]);

        $response = $this->actingAs($this->agent)->postJson('/api/v1/reservations', [
            'contact_id' => $otherContact->id,
            'starts_at' => now()->addDay()->toIso8601String(),
            'ends_at' => now()->addDay()->addHour()->toIso8601String(),
        ]);

        $response->assertUnprocessable();
    }

    public function test_slots_endpoint_rejects_invalid_date(): void
    {
        $response = $this->actingAs($this->agent)->getJson('/api/v1/reservations/slots?date=not-a-date');

        $response->assertUnprocessable();
    }

    public function test_past_reservation_rejected(): void
    {
        $response = $this->actingAs($this->agent)->postJson('/api/v1/reservations', [
            'contact_id' => $this->contact->id,
            'starts_at' => now()->subDay()->toIso8601String(),
            'ends_at' => now()->subDay()->addHour()->toIso8601String(),
        ]);

        $response->assertUnprocessable();
    }
}

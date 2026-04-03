<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\WhatsApp\CreateOrUpdateContact;
use App\Enums\ContactSource;
use App\Models\Contact;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateOrUpdateContactTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private CreateOrUpdateContact $action;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant',
        ]);

        $this->action = app(CreateOrUpdateContact::class);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function waData(array $overrides = []): array
    {
        return array_merge([
            'remoteJid'     => '573001234567@s.whatsapp.net',
            'pushName'      => 'Juan Pérez',
            'profilePicUrl' => 'https://example.com/pic.jpg',
        ], $overrides);
    }

    // -------------------------------------------------------------------------
    // Creation
    // -------------------------------------------------------------------------

    public function test_creates_new_contact_when_wa_id_does_not_exist(): void
    {
        $contact = $this->action->handle($this->tenant, $this->waData());

        $this->assertDatabaseHas('contacts', [
            'tenant_id' => $this->tenant->id,
            'wa_id'     => '573001234567@s.whatsapp.net',
            'phone'     => '573001234567',
            'push_name' => 'Juan Pérez',
        ]);

        $this->assertSame('573001234567@s.whatsapp.net', $contact->wa_id);
        $this->assertSame('573001234567', $contact->phone);
    }

    public function test_sets_source_to_whatsapp_on_create(): void
    {
        $contact = $this->action->handle($this->tenant, $this->waData());

        $this->assertSame(ContactSource::WhatsApp, $contact->source);
    }

    public function test_sets_first_contact_at_on_create(): void
    {
        $contact = $this->action->handle($this->tenant, $this->waData());

        $this->assertNotNull($contact->first_contact_at);
    }

    public function test_sets_last_contact_at_on_create(): void
    {
        $contact = $this->action->handle($this->tenant, $this->waData());

        $this->assertNotNull($contact->last_contact_at);
    }

    public function test_extracts_phone_number_from_remote_jid(): void
    {
        $contact = $this->action->handle($this->tenant, $this->waData([
            'remoteJid' => '15551234567@s.whatsapp.net',
        ]));

        $this->assertSame('15551234567', $contact->phone);
    }

    public function test_does_not_treat_lid_identifier_as_phone_without_hint(): void
    {
        $contact = $this->action->handle($this->tenant, $this->waData([
            'remoteJid' => '161752914342099@lid',
        ]));

        $this->assertSame('161752914342099@lid', $contact->wa_id);
        $this->assertNull($contact->phone);
    }

    // -------------------------------------------------------------------------
    // Update
    // -------------------------------------------------------------------------

    public function test_updates_push_name_when_contact_already_exists(): void
    {
        $this->action->handle($this->tenant, $this->waData(['pushName' => 'Nombre Viejo']));

        $contact = $this->action->handle($this->tenant, $this->waData(['pushName' => 'Nombre Nuevo']));

        $this->assertSame('Nombre Nuevo', $contact->push_name);
        $this->assertDatabaseCount('contacts', 1);
    }

    public function test_updates_profile_pic_url_when_contact_already_exists(): void
    {
        $this->action->handle($this->tenant, $this->waData(['profilePicUrl' => 'https://old.com/pic.jpg']));

        $contact = $this->action->handle($this->tenant, $this->waData(['profilePicUrl' => 'https://new.com/pic.jpg']));

        $this->assertSame('https://new.com/pic.jpg', $contact->profile_pic_url);
        $this->assertDatabaseCount('contacts', 1);
    }

    public function test_updates_last_contact_at_on_every_call(): void
    {
        $first = $this->action->handle($this->tenant, $this->waData());

        $this->travel(5)->minutes();

        $second = $this->action->handle($this->tenant, $this->waData());

        $this->assertTrue($second->last_contact_at->isAfter($first->last_contact_at));
    }

    public function test_does_not_overwrite_first_contact_at_on_update(): void
    {
        $first = $this->action->handle($this->tenant, $this->waData());

        $this->travel(5)->minutes();

        $second = $this->action->handle($this->tenant, $this->waData());

        $this->assertEquals(
            $first->first_contact_at->toDateTimeString(),
            $second->fresh()->first_contact_at->toDateTimeString()
        );
    }

    // -------------------------------------------------------------------------
    // Idempotency (no-duplicate guarantee — covers the race condition fix)
    // -------------------------------------------------------------------------

    public function test_calling_twice_with_same_wa_id_produces_exactly_one_contact(): void
    {
        $this->action->handle($this->tenant, $this->waData());
        $this->action->handle($this->tenant, $this->waData());

        $this->assertDatabaseCount('contacts', 1);
    }

    public function test_calling_three_times_with_same_wa_id_produces_exactly_one_contact(): void
    {
        $this->action->handle($this->tenant, $this->waData());
        $this->action->handle($this->tenant, $this->waData());
        $this->action->handle($this->tenant, $this->waData());

        $this->assertDatabaseCount('contacts', 1);
    }

    // -------------------------------------------------------------------------
    // Phone fallback (manual contact linking)
    // -------------------------------------------------------------------------

    public function test_links_manual_contact_by_phone_when_no_wa_id_match(): void
    {
        // Manual contact created without wa_id
        $manual = Contact::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'phone'     => '573001234567',
            'name'      => 'Creado Manual',
            'wa_id'     => null,
        ]);

        $contact = $this->action->handle($this->tenant, $this->waData([
            'remoteJid' => '573001234567@s.whatsapp.net',
        ]));

        $this->assertSame($manual->id, $contact->id);
        $this->assertSame('573001234567@s.whatsapp.net', $contact->wa_id);
        $this->assertDatabaseCount('contacts', 1);
    }

    public function test_links_contact_by_phone_hint_for_lid_identity(): void
    {
        $existing = Contact::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'phone'     => '573004410097',
            'name'      => 'My SEO Company',
            'wa_id'     => '573004410097@s.whatsapp.net',
        ]);

        $contact = $this->action->handle($this->tenant, $this->waData([
            'remoteJid' => '161752914342099@lid',
            'phone'     => '573004410097',
        ]));

        $this->assertSame($existing->id, $contact->id);
        $this->assertSame('161752914342099@lid', $contact->wa_id);
        $this->assertSame('573004410097', $contact->phone);
        $this->assertDatabaseCount('contacts', 1);
    }

    public function test_does_not_link_manual_contact_if_it_already_has_a_wa_id(): void
    {
        // Contact with same phone but different wa_id (already linked to someone else)
        Contact::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'phone'     => '573001234567',
            'wa_id'     => '573001234567@s.whatsapp.net',
        ]);

        $this->action->handle($this->tenant, $this->waData([
            'remoteJid' => '573001234567@s.whatsapp.net',
        ]));

        // Should update the existing one, not create a second
        $this->assertDatabaseCount('contacts', 1);
    }

    // -------------------------------------------------------------------------
    // Tenant isolation
    // -------------------------------------------------------------------------

    public function test_contact_from_other_tenant_is_not_updated(): void
    {
        $otherTenant = Tenant::create(['name' => 'Other', 'slug' => 'other']);

        $otherContact = Contact::withoutGlobalScopes()->create([
            'tenant_id' => $otherTenant->id,
            'wa_id'     => '573001234567@s.whatsapp.net',
            'phone'     => '573001234567',
            'push_name' => 'Nombre Ajeno',
        ]);

        $contact = $this->action->handle($this->tenant, $this->waData(['pushName' => 'Nombre Propio']));

        // New contact created for this tenant
        $this->assertNotSame($otherContact->id, $contact->id);
        $this->assertSame($this->tenant->id, $contact->tenant_id);
        $this->assertDatabaseCount('contacts', 2);

        // Other tenant's contact is untouched
        $this->assertDatabaseHas('contacts', [
            'id'        => $otherContact->id,
            'push_name' => 'Nombre Ajeno',
        ]);
    }

    public function test_does_not_link_manual_contact_from_other_tenant_by_phone(): void
    {
        $otherTenant = Tenant::create(['name' => 'Other', 'slug' => 'other']);

        Contact::withoutGlobalScopes()->create([
            'tenant_id' => $otherTenant->id,
            'phone'     => '573001234567',
            'wa_id'     => null,
        ]);

        $contact = $this->action->handle($this->tenant, $this->waData([
            'remoteJid' => '573001234567@s.whatsapp.net',
        ]));

        // Creates a new contact for the correct tenant, does not link to other tenant's contact
        $this->assertSame($this->tenant->id, $contact->tenant_id);
        $this->assertDatabaseCount('contacts', 2);
    }

    // -------------------------------------------------------------------------
    // Null handling
    // -------------------------------------------------------------------------

    public function test_handles_null_push_name_gracefully(): void
    {
        $contact = $this->action->handle($this->tenant, $this->waData(['pushName' => null]));

        $this->assertNull($contact->push_name);
    }

    public function test_handles_null_profile_pic_url_gracefully(): void
    {
        $contact = $this->action->handle($this->tenant, $this->waData(['profilePicUrl' => null]));

        $this->assertNull($contact->profile_pic_url);
    }

    public function test_preserves_existing_push_name_when_new_value_is_null(): void
    {
        $this->action->handle($this->tenant, $this->waData(['pushName' => 'Nombre Original']));

        $contact = $this->action->handle($this->tenant, $this->waData(['pushName' => null]));

        $this->assertSame('Nombre Original', $contact->push_name);
    }
}

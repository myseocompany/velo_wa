<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Channel;
use App\Enums\ConversationStatus;
use App\Enums\WaStatus;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Tenant;
use App\Models\WaHealthLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class WhatsAppLineDataMigrationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The data migration (000003) inserts a default WhatsAppLine per tenant
     * with legacy wa_* data and backfills conversations + health logs.
     * We replay it after seeding legacy state to verify the transformation.
     */
    public function test_migration_backfills_default_line_and_links_related_records(): void
    {
        $tenant = Tenant::create([
            'name' => 'Legacy',
            'slug' => 'legacy',
            'wa_instance_id' => 'tenant_legacy',
            'wa_status' => WaStatus::Connected,
            'wa_phone' => '573000000000',
            'wa_connected_at' => now(),
            'wa_health_consecutive_failures' => 2,
        ]);

        $contact = Contact::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'phone' => '573000000001',
            'wa_id' => '573000000001',
        ]);

        // Legacy conversation with null whatsapp_line_id (simulates pre-migration state)
        $conversationId = (string) Str::uuid();
        DB::table('conversations')->insert([
            'id' => $conversationId,
            'tenant_id' => $tenant->id,
            'contact_id' => $contact->id,
            'whatsapp_line_id' => null,
            'status' => ConversationStatus::Open->value,
            'channel' => Channel::WhatsApp->value,
            'message_count' => 0,
            'reopen_count' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $healthLogId = (string) Str::uuid();
        DB::table('wa_health_logs')->insert([
            'id' => $healthLogId,
            'tenant_id' => $tenant->id,
            'whatsapp_line_id' => null,
            'instance_name' => 'tenant_legacy',
            'state' => 'open',
            'is_healthy' => true,
            'response_ms' => 100,
            'checked_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Ensure no line exists before replay
        DB::table('whatsapp_lines')->where('tenant_id', $tenant->id)->delete();

        // Replay migration 000003 manually
        $migration = require database_path('migrations/2026_04_23_000003_migrate_tenant_wa_data_to_whatsapp_lines.php');
        $migration->up();

        $line = DB::table('whatsapp_lines')->where('tenant_id', $tenant->id)->first();

        $this->assertNotNull($line);
        $this->assertSame('Principal', $line->label);
        $this->assertTrue((bool) $line->is_default);
        $this->assertSame('tenant_legacy', $line->instance_id);
        $this->assertSame(WaStatus::Connected->value, $line->status);
        $this->assertSame('573000000000', $line->phone);
        $this->assertSame(2, (int) $line->health_consecutive_failures);

        $this->assertSame(
            $line->id,
            DB::table('conversations')->where('id', $conversationId)->value('whatsapp_line_id')
        );

        $this->assertSame(
            $line->id,
            DB::table('wa_health_logs')->where('id', $healthLogId)->value('whatsapp_line_id')
        );
    }
}

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $tenantIds = DB::table('tenants')
                ->whereNotNull('wa_instance_id')
                ->pluck('id')
                ->merge(
                    DB::table('conversations')
                        ->where('channel', 'whatsapp')
                        ->distinct()
                        ->pluck('tenant_id')
                )
                ->unique()
                ->values();

            foreach ($tenantIds as $tenantId) {
                $tenant = DB::table('tenants')->where('id', $tenantId)->first();

                if (! $tenant) {
                    continue;
                }

                $existingLineId = DB::table('whatsapp_lines')
                    ->where('tenant_id', $tenantId)
                    ->where('is_default', true)
                    ->value('id');

                $lineId = $existingLineId ?: (string) Str::uuid();

                if (! $existingLineId) {
                    DB::table('whatsapp_lines')->insert([
                        'id' => $lineId,
                        'tenant_id' => $tenantId,
                        'label' => 'Principal',
                        'instance_id' => $tenant->wa_instance_id,
                        'status' => $tenant->wa_instance_id ? ($tenant->wa_status ?? 'disconnected') : 'disconnected',
                        'phone' => $tenant->wa_instance_id ? $tenant->wa_phone : null,
                        'connected_at' => $tenant->wa_instance_id ? $tenant->wa_connected_at : null,
                        'is_default' => true,
                        'health_consecutive_failures' => (int) ($tenant->wa_health_consecutive_failures ?? 0),
                        'health_last_alert_at' => $tenant->wa_health_last_alert_at ?? null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                DB::table('conversations')
                    ->where('tenant_id', $tenantId)
                    ->where('channel', 'whatsapp')
                    ->whereNull('whatsapp_line_id')
                    ->update(['whatsapp_line_id' => $lineId]);

                DB::table('wa_health_logs')
                    ->where('tenant_id', $tenantId)
                    ->whereNull('whatsapp_line_id')
                    ->update(['whatsapp_line_id' => $lineId]);
            }
        });
    }

    public function down(): void
    {
        DB::table('wa_health_logs')->whereNotNull('whatsapp_line_id')->update(['whatsapp_line_id' => null]);
        DB::table('conversations')->whereNotNull('whatsapp_line_id')->update(['whatsapp_line_id' => null]);
        DB::table('whatsapp_lines')->delete();
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (in_array(DB::getDriverName(), ['pgsql', 'sqlite'], true)) {
            DB::statement('DROP INDEX IF EXISTS unique_active_conversation_per_contact');
        }

        Schema::table('conversations', function (Blueprint $table) {
            $table->foreignUuid('whatsapp_line_id')->nullable()->after('channel')->constrained('whatsapp_lines')->nullOnDelete();
            $table->index('whatsapp_line_id');
            $table->index(['tenant_id', 'whatsapp_line_id', 'status'], 'conversations_tenant_line_status_index');
        });

        if (in_array(DB::getDriverName(), ['pgsql', 'sqlite'], true)) {
            DB::statement(
                "CREATE UNIQUE INDEX IF NOT EXISTS unique_active_conversation_per_contact_line
                ON conversations (tenant_id, contact_id, whatsapp_line_id)
                WHERE status IN ('open', 'pending')"
            );
        }

        Schema::table('wa_health_logs', function (Blueprint $table) {
            $table->foreignUuid('whatsapp_line_id')->nullable()->after('tenant_id')->constrained('whatsapp_lines')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (in_array(DB::getDriverName(), ['pgsql', 'sqlite'], true)) {
            DB::statement('DROP INDEX IF EXISTS unique_active_conversation_per_contact_line');
        }

        Schema::table('wa_health_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('whatsapp_line_id');
        });

        Schema::table('conversations', function (Blueprint $table) {
            $table->dropIndex('conversations_tenant_line_status_index');
            $table->dropIndex(['whatsapp_line_id']);
            $table->dropConstrainedForeignId('whatsapp_line_id');
        });

        if (in_array(DB::getDriverName(), ['pgsql', 'sqlite'], true)) {
            DB::statement(
                "CREATE UNIQUE INDEX IF NOT EXISTS unique_active_conversation_per_contact
                ON conversations (tenant_id, contact_id)
                WHERE status IN ('open', 'pending')"
            );
        }
    }
};

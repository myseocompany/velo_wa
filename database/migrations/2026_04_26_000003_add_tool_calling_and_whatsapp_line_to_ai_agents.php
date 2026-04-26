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
        Schema::table('ai_agents', function (Blueprint $table) {
            $table->foreignUuid('whatsapp_line_id')->nullable()->after('tenant_id')
                ->constrained('whatsapp_lines')->nullOnDelete();
            $table->boolean('tool_calling_enabled')->default(false)->after('context_messages');
            $table->index(['tenant_id', 'whatsapp_line_id']);
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            // ai_agents does not use SoftDeletes, so the partial index only checks the nullable line.
            DB::statement("
                CREATE UNIQUE INDEX ai_agents_unique_per_line
                ON ai_agents (tenant_id, whatsapp_line_id)
                WHERE whatsapp_line_id IS NOT NULL
            ");
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS ai_agents_unique_per_line');
        }

        Schema::table('ai_agents', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'whatsapp_line_id']);
            $table->dropConstrainedForeignId('whatsapp_line_id');
            $table->dropColumn('tool_calling_enabled');
        });
    }
};

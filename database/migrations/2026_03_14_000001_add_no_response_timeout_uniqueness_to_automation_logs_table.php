<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const INDEX_NAME = 'automation_logs_no_response_timeout_once_idx';

    public function up(): void
    {
        if (! Schema::hasTable('automation_logs')) {
            return;
        }

        DB::statement(
            "CREATE UNIQUE INDEX IF NOT EXISTS " . self::INDEX_NAME .
            " ON automation_logs (automation_id, conversation_id)" .
            " WHERE trigger_type = 'no_response_timeout' AND status IN ('processing', 'success')"
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS ' . self::INDEX_NAME);
    }
};

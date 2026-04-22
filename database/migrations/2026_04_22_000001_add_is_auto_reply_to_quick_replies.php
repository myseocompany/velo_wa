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
        Schema::table('quick_replies', function (Blueprint $table) {
            $table->boolean('is_auto_reply')->default(false)->after('usage_count');
        });

        // Only one auto-reply per tenant (partial unique index — PostgreSQL only)
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('
                CREATE UNIQUE INDEX quick_replies_tenant_auto_reply_unique
                ON quick_replies (tenant_id)
                WHERE is_auto_reply = TRUE
            ');
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS quick_replies_tenant_auto_reply_unique');
        }

        Schema::table('quick_replies', function (Blueprint $table) {
            $table->dropColumn('is_auto_reply');
        });
    }
};

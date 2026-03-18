<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName  = config('activitylog.table_name', 'activity_log');
        $connection = config('activitylog.database_connection');

        Schema::connection($connection)->table($tableName, function (Blueprint $table) use ($tableName, $connection) {
            // Add missing 'event' column (spatie/laravel-activitylog v4+)
            if (! Schema::connection($connection)->hasColumn($tableName, 'event')) {
                $table->string('event')->nullable()->after('batch_uuid');
            }

            // Fix subject_id and causer_id: created as char(26) for ULID
            // but our PKs are UUIDs (36 chars) — widen to varchar(36)
            $table->string('subject_id', 36)->nullable()->change();
            $table->string('causer_id', 36)->nullable()->change();
        });
    }

    public function down(): void
    {
        $tableName  = config('activitylog.table_name', 'activity_log');
        $connection = config('activitylog.database_connection');

        Schema::connection($connection)->table($tableName, function (Blueprint $table) {
            $table->dropColumn('event');
            $table->string('subject_id', 26)->nullable()->change();
            $table->string('causer_id', 26)->nullable()->change();
        });
    }
};

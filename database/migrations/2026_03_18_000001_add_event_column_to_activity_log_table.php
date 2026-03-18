<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $table = config('activitylog.table_name', 'activity_log');
        $connection = config('activitylog.database_connection');

        Schema::connection($connection)->table($table, function (Blueprint $table) {
            if (! Schema::hasColumn(config('activitylog.table_name', 'activity_log'), 'event')) {
                $table->string('event')->nullable()->after('batch_uuid');
            }
        });
    }

    public function down(): void
    {
        $table = config('activitylog.table_name', 'activity_log');
        $connection = config('activitylog.database_connection');

        Schema::connection($connection)->table($table, function (Blueprint $table) {
            $table->dropColumn('event');
        });
    }
};

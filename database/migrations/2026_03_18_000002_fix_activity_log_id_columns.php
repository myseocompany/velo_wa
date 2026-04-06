<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $table      = config('activitylog.table_name', 'activity_log');
        $connection = config('activitylog.database_connection');
        $db         = DB::connection($connection);
        $driver     = $db->getDriverName();

        if ($driver !== 'pgsql') {
            return;
        }

        // Use raw SQL — doctrine/dbal ->change() does not reliably alter
        // CHAR columns in PostgreSQL. This widens char(26) → varchar(36)
        // so UUID primary keys (36 chars) are accepted.
        $db->statement("ALTER TABLE {$table} ALTER COLUMN subject_id TYPE VARCHAR(36)");
        $db->statement("ALTER TABLE {$table} ALTER COLUMN causer_id TYPE VARCHAR(36)");
    }

    public function down(): void
    {
        $table      = config('activitylog.table_name', 'activity_log');
        $connection = config('activitylog.database_connection');
        $db         = DB::connection($connection);
        $driver     = $db->getDriverName();

        if ($driver !== 'pgsql') {
            return;
        }

        $db->statement("ALTER TABLE {$table} ALTER COLUMN subject_id TYPE CHAR(26)");
        $db->statement("ALTER TABLE {$table} ALTER COLUMN causer_id TYPE CHAR(26)");
    }
};

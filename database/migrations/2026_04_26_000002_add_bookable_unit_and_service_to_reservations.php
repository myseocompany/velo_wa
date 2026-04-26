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
        Schema::table('reservations', function (Blueprint $table) {
            $table->foreignUuid('bookable_unit_id')->nullable()->after('assigned_to')
                ->constrained('bookable_units')->nullOnDelete();
            $table->string('service', 80)->nullable()->after('bookable_unit_id');
            $table->index(['tenant_id', 'bookable_unit_id', 'starts_at']);
            $table->index(['tenant_id', 'service']);
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('CREATE EXTENSION IF NOT EXISTS btree_gist');
            DB::statement("
                ALTER TABLE reservations
                ADD CONSTRAINT reservations_no_overlap
                EXCLUDE USING gist (
                    tenant_id WITH =,
                    bookable_unit_id WITH =,
                    tsrange(starts_at, ends_at, '[)') WITH &&
                )
                WHERE (
                    bookable_unit_id IS NOT NULL
                    AND deleted_at IS NULL
                    AND status NOT IN ('cancelled', 'no_show')
                )
            ");
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE reservations DROP CONSTRAINT IF EXISTS reservations_no_overlap');
        }

        Schema::table('reservations', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'service']);
            $table->dropIndex(['tenant_id', 'bookable_unit_id', 'starts_at']);
            $table->dropConstrainedForeignId('bookable_unit_id');
            $table->dropColumn('service');
        });
    }
};

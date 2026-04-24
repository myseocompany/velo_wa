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
        Schema::create('whatsapp_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('label', 100);
            $table->string('instance_id', 255)->unique()->nullable();
            $table->string('status', 30)->default('disconnected');
            $table->string('phone', 30)->nullable();
            $table->timestamp('connected_at')->nullable();
            $table->boolean('is_default')->default(false);
            $table->unsignedSmallInteger('health_consecutive_failures')->default(0);
            $table->timestamp('health_last_alert_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('tenant_id');
            $table->index(['tenant_id', 'is_default']);
            $table->index(['tenant_id', 'status']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                'CREATE UNIQUE INDEX whatsapp_lines_tenant_default_unique ON whatsapp_lines (tenant_id) WHERE is_default = true AND deleted_at IS NULL'
            );
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS whatsapp_lines_tenant_default_unique');
        }

        Schema::dropIfExists('whatsapp_lines');
    }
};

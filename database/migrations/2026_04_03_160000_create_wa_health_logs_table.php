<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wa_health_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('instance_name', 120);
            $table->string('state', 40)->nullable();
            $table->boolean('is_healthy')->default(false);
            $table->unsignedInteger('response_ms')->nullable();
            $table->string('error_message', 500)->nullable();
            $table->timestamp('checked_at')->useCurrent();
            $table->timestamps();

            $table->index(['tenant_id', 'checked_at']);
            $table->index(['tenant_id', 'is_healthy']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_health_logs');
    }
};


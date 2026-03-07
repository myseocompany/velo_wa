<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assignment_rules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->string('name');
            $table->string('type'); // round_robin|least_busy|tag_based|manual
            $table->unsignedSmallInteger('priority')->default(100); // lower = higher priority
            $table->boolean('is_active')->default(true);
            $table->jsonb('config')->default('{}'); // type-specific config
            $table->timestamps();

            $table->index(['tenant_id', 'priority']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assignment_rules');
    }
};

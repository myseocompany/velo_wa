<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tags', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->string('name', 80);
            $table->string('slug', 100);
            $table->string('color', 7)->default('#6366f1');
            $table->boolean('exclude_from_metrics')->default(false);
            $table->timestamps();

            $table->unique(['tenant_id', 'slug']);
            // Fast lookup when filtering DT1 by exclude_from_metrics
            $table->index(['tenant_id', 'exclude_from_metrics']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tags');
    }
};

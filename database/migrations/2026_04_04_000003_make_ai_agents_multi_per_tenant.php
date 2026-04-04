<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_agents', function (Blueprint $table) {
            try {
                $table->dropUnique('ai_agents_tenant_id_unique');
            } catch (\Throwable $e) {
                // Index may not exist in some environments.
            }

            $table->boolean('is_default')->default(false)->after('is_enabled');
            $table->index(['tenant_id', 'is_default']);
        });
    }

    public function down(): void
    {
        Schema::table('ai_agents', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'is_default']);
            $table->dropColumn('is_default');
            $table->unique('tenant_id');
        });
    }
};

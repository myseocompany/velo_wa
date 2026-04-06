<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_admin_audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('platform_admin_id')->constrained('platform_admins')->cascadeOnDelete();
            $table->string('action');                          // e.g. impersonate, update_plan, disconnect_wa
            $table->string('target_type')->nullable();         // e.g. App\Models\Tenant
            $table->string('target_id')->nullable();           // UUID of affected tenant
            $table->string('ip_address', 45)->nullable();
            $table->jsonb('metadata')->nullable();             // extra context
            $table->timestamp('created_at')->useCurrent();

            $table->index('platform_admin_id');
            $table->index('action');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_admin_audit_logs');
    }
};

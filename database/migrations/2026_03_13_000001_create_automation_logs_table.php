<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automation_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->uuid('automation_id')->index();
            $table->uuid('conversation_id')->nullable()->index();
            $table->string('trigger_type');
            $table->string('action_type');
            $table->string('status'); // success | failed
            $table->text('error_message')->nullable();
            $table->timestamp('triggered_at');

            $table->foreign('automation_id')->references('id')->on('automations')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_logs');
    }
};

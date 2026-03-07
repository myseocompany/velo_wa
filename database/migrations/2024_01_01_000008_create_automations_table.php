<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->string('name');
            $table->string('trigger_type'); // new_conversation|keyword|outside_hours|no_response_timeout
            $table->jsonb('trigger_config')->default('{}');
            $table->string('action_type'); // send_message|assign_agent|add_tag|move_stage
            $table->jsonb('action_config')->default('{}');
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('priority')->default(100);
            $table->unsignedInteger('execution_count')->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'trigger_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automations');
    }
};

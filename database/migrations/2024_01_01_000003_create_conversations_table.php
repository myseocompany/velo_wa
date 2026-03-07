<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->uuid('contact_id')->index();
            $table->string('status')->default('open'); // open|pending|closed
            $table->string('channel')->default('whatsapp'); // whatsapp|manual
            $table->uuid('assigned_to')->nullable()->index();
            $table->timestamp('assigned_at')->nullable();

            // Metrics (for Dt1 calculation)
            $table->timestamp('first_message_at')->nullable();
            $table->timestamp('first_response_at')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->unsignedInteger('message_count')->default(0);

            $table->timestamp('closed_at')->nullable();
            $table->uuid('closed_by')->nullable(); // null = auto-close
            $table->unsignedSmallInteger('reopen_count')->default(0);

            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'last_message_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};

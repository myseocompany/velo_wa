<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();

            // WhatsApp
            $table->string('wa_instance_id')->nullable()->unique();
            $table->string('wa_status')->default('disconnected'); // disconnected|qr_pending|connected|banned
            $table->string('wa_phone', 30)->nullable();
            $table->timestamp('wa_connected_at')->nullable();

            // Plan limits
            $table->unsignedSmallInteger('max_agents')->default(5);
            $table->unsignedInteger('max_contacts')->default(10000);
            $table->unsignedSmallInteger('media_retention_days')->default(90);

            // Config
            $table->string('timezone', 64)->default('America/Bogota');
            $table->jsonb('business_hours')->nullable(); // { mon: {open: "09:00", close: "18:00"}, ... }
            $table->unsignedSmallInteger('auto_close_hours')->default(24);

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};

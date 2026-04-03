<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('user_id');
            $table->uuid('assigned_to')->nullable();
            $table->uuid('contact_id')->nullable();
            $table->uuid('conversation_id')->nullable();
            $table->uuid('deal_id')->nullable();
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('reminded_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->enum('priority', ['low', 'medium', 'high'])->default('medium');
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users');
            $table->foreign('assigned_to')->references('id')->on('users')->nullOnDelete();
            $table->foreign('contact_id')->references('id')->on('contacts')->nullOnDelete();
            $table->foreign('conversation_id')->references('id')->on('conversations')->nullOnDelete();
            $table->foreign('deal_id')->references('id')->on('pipeline_deals')->nullOnDelete();

            // Listar tareas pendientes por agente
            $table->index(['tenant_id', 'assigned_to', 'completed_at']);
            // Scheduler de recordatorios
            $table->index(['tenant_id', 'due_at']);
            // Tareas de un contacto
            $table->index('contact_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};

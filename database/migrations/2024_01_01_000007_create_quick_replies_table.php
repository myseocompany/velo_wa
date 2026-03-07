<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quick_replies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->string('shortcut', 50); // /precio, /horario
            $table->string('title');
            $table->text('body');
            $table->boolean('has_variables')->default(false); // supports {{contact_name}}, etc.
            $table->string('category', 50)->default('general'); // ventas|soporte|general
            $table->unsignedInteger('usage_count')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'shortcut']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quick_replies');
    }
};

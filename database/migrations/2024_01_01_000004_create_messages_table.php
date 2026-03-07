<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('conversation_id')->index();
            $table->uuid('tenant_id')->index(); // denormalized for query performance
            $table->string('direction', 3); // in|out
            $table->text('body')->nullable();
            $table->string('media_url')->nullable();
            $table->string('media_type', 20)->nullable(); // image|video|audio|document|sticker
            $table->string('media_mime_type', 80)->nullable();
            $table->string('media_filename')->nullable();
            $table->string('status', 20)->default('pending'); // pending|sent|delivered|read|failed
            $table->string('wa_message_id')->nullable()->index(); // WA internal message ID
            $table->text('error_message')->nullable();
            $table->uuid('sent_by')->nullable(); // null for inbound
            $table->boolean('is_automated')->default(false);
            $table->timestamps();

            $table->index(['conversation_id', 'created_at']);
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->string('wa_id', 30)->nullable(); // WhatsApp ID (phone@s.whatsapp.net)
            $table->string('phone', 30)->nullable();
            $table->string('name')->nullable();
            $table->string('push_name')->nullable(); // WA display name
            $table->string('profile_pic_url')->nullable();
            $table->string('email')->nullable();
            $table->string('company')->nullable();
            $table->text('notes')->nullable();
            $table->jsonb('tags')->default('[]');
            $table->jsonb('custom_fields')->default('{}');
            $table->uuid('assigned_to')->nullable()->index();
            $table->string('source')->default('whatsapp'); // whatsapp|manual|import
            $table->timestamp('first_contact_at')->nullable();
            $table->timestamp('last_contact_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'wa_id']);
            $table->index(['tenant_id', 'phone']);
        });

        // GIN index for tags (PostgreSQL-specific). Skip in SQLite tests.
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('CREATE INDEX contacts_tags_gin ON contacts USING GIN (tags)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};

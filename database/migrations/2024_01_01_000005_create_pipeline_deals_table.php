<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pipeline_deals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->uuid('contact_id')->index();
            $table->uuid('conversation_id')->nullable()->index();
            $table->string('title');
            $table->string('stage')->default('lead'); // lead|qualified|proposal|negotiation|closed_won|closed_lost
            $table->decimal('value', 15, 2)->nullable();
            $table->string('currency', 3)->default('COP');

            // Stage timestamps
            $table->timestamp('lead_at')->nullable();
            $table->timestamp('qualified_at')->nullable();
            $table->timestamp('proposal_at')->nullable();
            $table->timestamp('negotiation_at')->nullable();
            $table->timestamp('closed_at')->nullable();

            $table->string('lost_reason')->nullable();
            $table->string('won_product')->nullable();
            $table->uuid('assigned_to')->nullable()->index();
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'stage']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pipeline_deals');
    }
};

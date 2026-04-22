<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            // Timestamp of the first non-automated, non-auto-reply outbound message
            $table->timestampTz('first_human_response_at')->nullable()->after('first_response_at');

            // DT1 in business minutes (null = lead not yet responded to, or not first conversation)
            $table->integer('dt1_minutes_business')->nullable()->after('first_human_response_at');
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropColumn(['first_human_response_at', 'dt1_minutes_business']);
        });
    }
};

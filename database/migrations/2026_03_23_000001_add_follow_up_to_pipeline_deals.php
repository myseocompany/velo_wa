<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pipeline_deals', function (Blueprint $table) {
            $table->timestamp('follow_up_at')->nullable()->after('closed_at');
            $table->string('follow_up_note', 1000)->nullable()->after('follow_up_at');
        });
    }

    public function down(): void
    {
        Schema::table('pipeline_deals', function (Blueprint $table) {
            $table->dropColumn(['follow_up_at', 'follow_up_note']);
        });
    }
};

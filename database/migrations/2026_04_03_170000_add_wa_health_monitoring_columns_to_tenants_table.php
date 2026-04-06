<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->unsignedInteger('wa_health_consecutive_failures')->default(0)->after('onboarding_vertical');
            $table->timestamp('wa_health_last_alert_at')->nullable()->after('wa_health_consecutive_failures');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['wa_health_consecutive_failures', 'wa_health_last_alert_at']);
        });
    }
};


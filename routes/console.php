<?php

use App\Jobs\CheckInstanceHealth;
use App\Jobs\ProcessDueFollowUps;
use App\Jobs\ProcessNoResponseTimeout;
use App\Jobs\RecalculateMetrics;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Check WhatsApp instance health every 5 minutes
Schedule::job(new CheckInstanceHealth)->everyFiveMinutes();

// Fire no-response-timeout automations every minute
Schedule::job(new ProcessNoResponseTimeout)->everyMinute();

// Pre-compute and cache dashboard stats for all tenants every hour
Schedule::job(new RecalculateMetrics)->hourly()->onOneServer();

// Notify agents about overdue deal follow-ups every 15 minutes
Schedule::job(new ProcessDueFollowUps)->everyFifteenMinutes()->onOneServer();

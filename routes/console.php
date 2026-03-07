<?php

use App\Jobs\CheckInstanceHealth;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Check WhatsApp instance health every 5 minutes
Schedule::job(new CheckInstanceHealth)->everyFiveMinutes();

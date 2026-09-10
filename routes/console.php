<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// SWS: refresh stock rollups and sweep for low-stock/expiry conditions.
Schedule::command('inventory:check-alerts')->dailyAt('01:00');
Schedule::command('suppliers:check-compliance')->dailyAt('01:15')->withoutOverlapping();
Schedule::command('procurement:close-expired-rfqs')->everyMinute()->withoutOverlapping();

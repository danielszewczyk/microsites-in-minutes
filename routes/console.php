<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Keep the minute/hourly analytics tiers within their retention windows.
Schedule::command('stats:prune')->dailyAt('03:10')->onOneServer();

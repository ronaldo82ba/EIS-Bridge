<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('observability:check')
    ->everyTenMinutes()
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('queues:broadcast')
    ->everyThirtySeconds()
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('licenses:check-renewals')
    ->daily()
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('certificates:scan-expiry')
    ->dailyAt('01:00')
    ->withoutOverlapping()
    ->runInBackground();

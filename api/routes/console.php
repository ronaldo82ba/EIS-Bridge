<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

$schedulerLog = storage_path('logs/scheduler.log');

Schedule::command('observability:check')
    ->everyTenMinutes()
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo($schedulerLog);

Schedule::command('queues:broadcast')
    ->everyThirtySeconds()
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo($schedulerLog);

Schedule::command('licenses:check-renewals')
    ->daily()
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo($schedulerLog);

Schedule::command('certificates:scan-expiry')
    ->dailyAt('01:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo($schedulerLog);

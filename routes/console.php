<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule the file deletion processor to run every hour
Schedule::command('scheduled-deletions:process')
    ->hourly()
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/scheduled-deletions.log'));

// Schedule the expired downloads cleanup to run every hour
Schedule::command('downloads:cleanup-expired')
    ->hourly()
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/cleanup-expired-downloads.log'));

// Schedule the bank transfer checker to run every 5 seconds
Schedule::command('bank-transfer:check --force')
    ->cron('*/5 * * * * *') // Every 5 seconds using cron expression
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/bank-transfer-check.log'));

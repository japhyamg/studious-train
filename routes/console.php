<?php

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Scheduled Cron Jobs
|--------------------------------------------------------------------------
| Run `php artisan schedule:run` via server crontab:
| * * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
|
*/

// CDD/EDD Review Scheduler — runs daily at 1:00 AM
Schedule::call(function () {
    app(\App\Http\Controllers\CronJobController::class)->reviewScheduler();
})->daily()->at('01:00')->name('review-scheduler')->withoutOverlapping();

// 24-Hour Transaction Rule Engine — runs daily at 2:00 AM
Schedule::call(function () {
    app(\App\Http\Controllers\CronJobController::class)->dailyRuleEngine();
})->daily()->at('02:00')->name('24hr-rule-engine')->withoutOverlapping();

// Watchlist Screening — runs every Sunday at 3:00 AM
Schedule::call(function () {
    app(\App\Http\Controllers\CronJobController::class)->watchListScreening();
})->weekly()->sundays()->at('03:00')->name('watchlist-screening')->withoutOverlapping();

// Auto Risk Rate New Customers — runs daily at 4:00 AM
Schedule::call(function () {
    app(\App\Http\Controllers\CronJobController::class)->riskRateNewCustomers();
})->daily()->at('04:00')->name('risk-rate-new-customers')->withoutOverlapping();

// CTR Generation — runs daily at 5:00 AM
Schedule::call(function () {
    app(\App\Http\Controllers\CronJobController::class)->generateCtr();
})->daily()->at('05:00')->name('generate-ctr')->withoutOverlapping();

// Customer Data Sync — runs at configurable interval (default every 24 hours)
Schedule::call(function () {
    app(\App\Http\Controllers\CronJobController::class)->customerSync();
})->cron(
    // Build cron expression from settings: every N hours
    '0 */' . (\App\Services\CustomerSyncService::getSyncIntervalHours()) . ' * * *'
)->name('customer-sync')->withoutOverlapping();

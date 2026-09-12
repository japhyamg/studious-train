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

// Sanctions list refresh (OFAC, UN, Nigerian) — daily at 3:15 AM
Schedule::command('sanctions:sync')
    ->dailyAt('03:15')
    ->name('sanctions-sync')
    ->withoutOverlapping();

// Nightly PAS screening of recently onboarded customers — daily at 2:30 AM
Schedule::command('pas:screen-recent-customers')
    ->dailyAt('02:30')
    ->name('pas-screening')
    ->withoutOverlapping();

// Pre-emptive behavioural alert scoring — daily at 2:45 AM
Schedule::command('preemptive:score')
    ->dailyAt('02:45')
    ->name('preemptive-score')
    ->withoutOverlapping();

// Auto Risk Rate New Customers — runs daily at 4:00 AM
Schedule::call(function () {
    app(\App\Http\Controllers\CronJobController::class)->riskRateNewCustomers();
})->daily()->at('04:00')->name('risk-rate-new-customers')->withoutOverlapping();

// Automated full risk rating (default profile) — nightly at 23:59.
Schedule::command('risk:rate')
    ->dailyAt('23:59')
    ->name('risk-rating')
    ->withoutOverlapping();

// Demo cadence — re-rate every 2 minutes when demo mode is enabled.
// The command exits early unless settings: risk_rating_demo_mode = true.
Schedule::command('risk:rate --demo')
    ->everyTwoMinutes()
    ->name('risk-rating-demo')
    ->withoutOverlapping();

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

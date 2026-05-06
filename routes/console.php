<?php

declare(strict_types=1);

use App\Application\Jobs\RollupAnalyticsDailyJob;
use App\Application\Jobs\RollupAnalyticsTodayJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Yesterday's full rollup at 01:00 — leaves a one-hour grace for
// late-arriving events with retroactive `occurred_at` timestamps.
Schedule::job(RollupAnalyticsDailyJob::class)
    ->dailyAt('01:00')
    ->onOneServer();

// Partial rollup for today's running totals (every 30 min). The
// action is idempotent (delete-then-insert per date), so consecutive
// passes overwrite each other cleanly.
Schedule::job(RollupAnalyticsTodayJob::class)
    ->everyThirtyMinutes()
    ->onOneServer();

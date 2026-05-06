<?php

declare(strict_types=1);

namespace App\Application\Jobs;

use App\Domain\Analytics\Actions\RollupAnalyticsForDateAction;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Rolls up the *prior* calendar day's events into the three rollup
 * tables. Scheduled at 01:00 server time so any late-arriving events
 * (mobile clients with retroactive `occurred_at`) have a one-hour
 * grace window. Reruns are idempotent because the action does
 * delete-then-insert.
 */
final class RollupAnalyticsDailyJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function handle(RollupAnalyticsForDateAction $action): void
    {
        $action->execute(CarbonImmutable::yesterday());
    }
}

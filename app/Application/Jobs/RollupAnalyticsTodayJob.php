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
 * Partial rollup for the current calendar day. Scheduled every 30
 * minutes so the dashboard can show today's running totals without
 * scanning the raw `analytics_events` table for the whole day.
 */
final class RollupAnalyticsTodayJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function handle(RollupAnalyticsForDateAction $action): void
    {
        $action->execute(CarbonImmutable::now()->startOfDay());
    }
}

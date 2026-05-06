<?php

declare(strict_types=1);

namespace App\Application\Jobs\Etl;

use App\Domain\Admin\Imports\Models\ImportJob;
use App\Domain\Migration\Etl\DataTransferObjects\EtlSubJobResult;
use App\Domain\Migration\Etl\Support\EtlJobReporter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Stage 2 — materialise per-day rows for every existing subscription:
 *   • One `reading_plan_subscription_days` row per day in `[date_from, date_to]`
 *   • Mark `completed_at = now()` for any day-position present in the
 *     legacy `plan_progress` JSON column.
 *
 * The legacy `reading_plan_subscription_days_legacy` table is dropped at
 * the end of a successful pass. Idempotent across re-runs because the
 * unique `(subscription_id, reading_plan_day_id)` index acts as a guard.
 */
final class EtlReadingPlanSubscriptionsJob extends BaseEtlJob
{
    public static function slug(): string
    {
        return 'etl_reading_plan_subscriptions';
    }

    protected function execute(EtlJobReporter $reporter, ImportJob $importJob): EtlSubJobResult
    {
        if (
            ! Schema::hasTable('reading_plan_subscriptions')
            || ! Schema::hasTable('reading_plan_subscription_days')
            || ! Schema::hasTable('reading_plan_days')
        ) {
            return new EtlSubJobResult;
        }

        $subscriptions = DB::table('reading_plan_subscriptions')
            ->orderBy('id')
            ->get();

        $total = $subscriptions->count();
        $processed = 0;
        $succeeded = 0;
        /** @var list<array{row?: int|string, message: string}> $errors */
        $errors = [];

        foreach ($subscriptions as $subscription) {
            $processed++;

            try {
                $succeeded += $this->materialiseDays($subscription);
            } catch (\Throwable $exception) {
                $errors[] = [
                    'row' => (int) $subscription->id,
                    'message' => $exception->getMessage(),
                ];
                $reporter->appendError($importJob, $errors[count($errors) - 1]);
            }

            if ($processed % 25 === 0) {
                $reporter->progress($importJob, $processed, $total);
            }
        }

        $this->dropLegacyDaysTableIfClean($errors);

        return new EtlSubJobResult(
            processed: $processed,
            succeeded: $succeeded,
            errors: $errors,
        );
    }

    private function materialiseDays(object $subscription): int
    {
        /** @var \stdClass $subscription */
        $startDate = $subscription->start_date ?? null;
        if (! is_string($startDate) || $startDate === '') {
            return 0;
        }

        $startsAt = Carbon::parse($startDate);

        $days = DB::table('reading_plan_days')
            ->where('reading_plan_id', $subscription->reading_plan_id)
            ->orderBy('position')
            ->get(['id', 'position']);

        if ($days->isEmpty()) {
            return 0;
        }

        $progress = $this->resolveLegacyProgress($subscription);

        $rows = [];
        foreach ($days as $day) {
            /** @var \stdClass $day */
            $scheduled = $startsAt->copy()->addDays((int) $day->position - 1);
            $rows[] = [
                'reading_plan_subscription_id' => $subscription->id,
                'reading_plan_day_id' => $day->id,
                'scheduled_date' => $scheduled->toDateString(),
                'completed_at' => in_array((int) $day->position, $progress, true)
                    ? Carbon::now()->toDateTimeString()
                    : null,
                'created_at' => Carbon::now()->toDateTimeString(),
                'updated_at' => Carbon::now()->toDateTimeString(),
            ];
        }

        // INSERT IGNORE leans on the unique (subscription_id, day_id) index
        // so re-running cleanly skips already-inserted days.
        DB::table('reading_plan_subscription_days')->insertOrIgnore($rows);

        return count($rows);
    }

    /**
     * @return list<int>
     */
    private function resolveLegacyProgress(object $subscription): array
    {
        if (! property_exists($subscription, 'plan_progress')) {
            return [];
        }

        $raw = $subscription->plan_progress;
        if (! is_string($raw) || $raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            return [];
        }

        $positions = [];
        foreach ($decoded as $entry) {
            if (is_int($entry) || (is_string($entry) && ctype_digit($entry))) {
                $positions[] = (int) $entry;
            }
        }

        return $positions;
    }

    /**
     * @param  list<array{row?: int|string, message: string}>  $errors
     */
    private function dropLegacyDaysTableIfClean(array $errors): void
    {
        if ($errors !== []) {
            return;
        }

        if (Schema::hasTable('reading_plan_subscription_days_legacy')) {
            Schema::drop('reading_plan_subscription_days_legacy');
        }
    }
}

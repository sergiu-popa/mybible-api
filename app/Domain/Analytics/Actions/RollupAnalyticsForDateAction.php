<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Idempotent per-date aggregator. Materialises three rollup tables
 * for a given calendar date inside a transaction:
 *
 * - `analytics_daily_rollups` — one row per
 *   (date, event_type, subject_type, subject_id, language).
 * - `analytics_user_active_daily` — one row per (date, user_id) for
 *   users with ≥1 event on that date.
 * - `analytics_device_active_daily` — one row per (date, device_id).
 *
 * Re-running for the same date deletes existing rows and re-inserts,
 * so partial-today reruns and end-of-day reconciliation are safe.
 *
 * Sentinels (`''` for nullable string columns, `0` for nullable
 * subject_id) are written so the composite primary key works in
 * MySQL — read endpoints translate them back to `null`.
 */
final class RollupAnalyticsForDateAction
{
    public function execute(CarbonImmutable $date): void
    {
        $start = $date->startOfDay();
        $end = $date->endOfDay();
        $dateString = $date->toDateString();

        DB::transaction(function () use ($start, $end, $dateString): void {
            DB::table('analytics_daily_rollups')->where('date', $dateString)->delete();
            DB::table('analytics_user_active_daily')->where('date', $dateString)->delete();
            DB::table('analytics_device_active_daily')->where('date', $dateString)->delete();

            $this->insertEventRollups($start, $end, $dateString);
            $this->insertUserActiveDaily($start, $end, $dateString);
            $this->insertDeviceActiveDaily($start, $end, $dateString);
        });
    }

    private function insertEventRollups(
        CarbonImmutable $start,
        CarbonImmutable $end,
        string $dateString,
    ): void {
        $rows = DB::table('analytics_events')
            ->whereBetween('occurred_at', [$start, $end])
            ->selectRaw(
                "event_type,
                 COALESCE(subject_type, '') AS subject_type,
                 COALESCE(subject_id, 0) AS subject_id,
                 COALESCE(language, '') AS language,
                 COUNT(*) AS event_count,
                 COUNT(DISTINCT user_id) AS unique_users,
                 COUNT(DISTINCT device_id) AS unique_devices",
            )
            ->groupBy('event_type', 'subject_type', 'subject_id', 'language')
            ->get();

        if ($rows->isEmpty()) {
            return;
        }

        $insert = $rows->map(static fn ($row): array => [
            'date' => $dateString,
            'event_type' => $row->event_type,
            'subject_type' => $row->subject_type,
            'subject_id' => (int) $row->subject_id,
            'language' => $row->language,
            'event_count' => (int) $row->event_count,
            'unique_users' => (int) $row->unique_users,
            'unique_devices' => (int) $row->unique_devices,
        ])->all();

        DB::table('analytics_daily_rollups')->insert($insert);
    }

    private function insertUserActiveDaily(
        CarbonImmutable $start,
        CarbonImmutable $end,
        string $dateString,
    ): void {
        $rows = DB::table('analytics_events')
            ->whereBetween('occurred_at', [$start, $end])
            ->whereNotNull('user_id')
            ->select('user_id')
            ->groupBy('user_id')
            ->get();

        if ($rows->isEmpty()) {
            return;
        }

        $insert = $rows->map(static fn ($row): array => [
            'date' => $dateString,
            'user_id' => (int) $row->user_id,
        ])->all();

        DB::table('analytics_user_active_daily')->insert($insert);
    }

    private function insertDeviceActiveDaily(
        CarbonImmutable $start,
        CarbonImmutable $end,
        string $dateString,
    ): void {
        $rows = DB::table('analytics_events')
            ->whereBetween('occurred_at', [$start, $end])
            ->whereNotNull('device_id')
            ->select('device_id')
            ->groupBy('device_id')
            ->get();

        if ($rows->isEmpty()) {
            return;
        }

        $insert = $rows->map(static fn ($row): array => [
            'date' => $dateString,
            'device_id' => (string) $row->device_id,
        ])->all();

        DB::table('analytics_device_active_daily')->insert($insert);
    }
}

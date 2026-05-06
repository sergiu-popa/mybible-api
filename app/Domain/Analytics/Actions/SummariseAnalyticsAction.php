<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Actions;

use App\Domain\Analytics\DataTransferObjects\AnalyticsRangeQueryData;
use Illuminate\Support\Facades\DB;

/**
 * Powers the `summary` admin endpoint. Reads from the rollup tables
 * exclusively (raw events table is never scanned at request time).
 */
final class SummariseAnalyticsAction
{
    /**
     * @return array{
     *     total_events: int,
     *     dau: int,
     *     mau: int,
     *     top_event_types: array<int, array{event_type: string, count: int}>
     * }
     */
    public function execute(AnalyticsRangeQueryData $query): array
    {
        $from = $query->from->toDateString();
        $to = $query->to->toDateString();
        $mauStart = $query->to->copy()->subDays(27)->toDateString();

        $totalEvents = (int) DB::table('analytics_daily_rollups')
            ->whereBetween('date', [$from, $to])
            ->sum('event_count');

        $dau = (int) DB::table('analytics_user_active_daily')
            ->where('date', $to)
            ->count();

        $mau = (int) DB::table('analytics_user_active_daily')
            ->whereBetween('date', [$mauStart, $to])
            ->distinct()
            ->count('user_id');

        $top = DB::table('analytics_daily_rollups')
            ->whereBetween('date', [$from, $to])
            ->selectRaw('event_type, SUM(event_count) AS count')
            ->groupBy('event_type')
            ->orderByDesc('count')
            ->limit(5)
            ->get();

        $topEventTypes = $top->map(static fn ($row): array => [
            'event_type' => (string) $row->event_type,
            'count' => (int) $row->count,
        ])->all();

        return [
            'total_events' => $totalEvents,
            'dau' => $dau,
            'mau' => $mau,
            'top_event_types' => $topEventTypes,
        ];
    }
}

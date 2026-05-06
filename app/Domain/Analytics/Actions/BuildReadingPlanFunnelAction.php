<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Actions;

use App\Domain\Analytics\DataTransferObjects\ReadingPlanFunnelQueryData;
use App\Domain\Analytics\Enums\EventType;
use Illuminate\Support\Facades\DB;

/**
 * Computes the reading-plan funnel: starts, day-N completions
 * (1..30), abandons (with at_day distribution), completes.
 *
 * Joins back to raw `analytics_events` (filtered to the four
 * `reading_plan.subscription.*` types) because the rollup loses
 * `metadata.day_position` / `metadata.at_day_position` — the
 * per-user dropoff distribution lives there.
 */
final class BuildReadingPlanFunnelAction
{
    /**
     * @return array{
     *     started: int,
     *     completed_per_day: array<int, array{day: int, count: int}>,
     *     abandoned: int,
     *     abandoned_at_day: array<int, array{day: int, count: int}>,
     *     completed: int,
     * }
     */
    public function execute(ReadingPlanFunnelQueryData $query): array
    {
        $base = DB::table('analytics_events')
            ->whereBetween('occurred_at', [$query->range->from, $query->range->to]);

        if ($query->planId !== null) {
            $planId = $query->planId;
            $base->where(function ($q) use ($planId): void {
                $q->where('subject_id', $planId)
                    ->orWhereRaw("JSON_EXTRACT(metadata, '$.plan_id') = ?", [$planId]);
            });
        }

        $started = (int) (clone $base)
            ->where('event_type', EventType::ReadingPlanSubscriptionStarted->value)
            ->count();

        $completed = (int) (clone $base)
            ->where('event_type', EventType::ReadingPlanSubscriptionCompleted->value)
            ->count();

        $abandoned = (int) (clone $base)
            ->where('event_type', EventType::ReadingPlanSubscriptionAbandoned->value)
            ->count();

        $completedPerDayRows = (clone $base)
            ->where('event_type', EventType::ReadingPlanSubscriptionDayCompleted->value)
            ->selectRaw("CAST(JSON_EXTRACT(metadata, '$.day_position') AS UNSIGNED) AS day, COUNT(*) AS count")
            ->groupBy('day')
            ->orderBy('day')
            ->get();

        $abandonedAtDayRows = (clone $base)
            ->where('event_type', EventType::ReadingPlanSubscriptionAbandoned->value)
            ->selectRaw("CAST(JSON_EXTRACT(metadata, '$.at_day_position') AS UNSIGNED) AS day, COUNT(*) AS count")
            ->groupBy('day')
            ->orderBy('day')
            ->get();

        return [
            'started' => $started,
            'completed_per_day' => $completedPerDayRows->map(static fn ($r): array => [
                'day' => (int) $r->day,
                'count' => (int) $r->count,
            ])->all(),
            'abandoned' => $abandoned,
            'abandoned_at_day' => $abandonedAtDayRows->map(static fn ($r): array => [
                'day' => (int) $r->day,
                'count' => (int) $r->count,
            ])->all(),
            'completed' => $completed,
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Actions;

use App\Domain\Analytics\DataTransferObjects\EventCountsQueryData;
use Illuminate\Support\Facades\DB;

/**
 * Powers the `event-counts` admin endpoint. Returns a per-day series
 * for one event type, optionally pivoted by `language` or
 * `subject_id`. Sentinels written by the rollup are translated back
 * to `null` here so callers see clean JSON.
 */
final class ListEventCountsAction
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function execute(EventCountsQueryData $query): array
    {
        $from = $query->range->from->toDateString();
        $to = $query->range->to->toDateString();

        $select = ['date', DB::raw('SUM(event_count) AS count')];
        $groupBy = ['date'];

        if ($query->groupBy === 'language') {
            $select[] = 'language';
            $groupBy[] = 'language';
        } elseif ($query->groupBy === 'subject_id') {
            $select[] = 'subject_type';
            $select[] = 'subject_id';
            $groupBy[] = 'subject_type';
            $groupBy[] = 'subject_id';
        }

        $rows = DB::table('analytics_daily_rollups')
            ->where('event_type', $query->eventType->value)
            ->whereBetween('date', [$from, $to])
            ->select($select)
            ->groupBy($groupBy)
            ->orderBy('date')
            ->get();

        return $rows->map(function ($row) use ($query): array {
            $out = [
                'date' => (string) $row->date,
                'count' => (int) $row->count,
            ];

            if ($query->groupBy === 'language') {
                $language = isset($row->language) ? (string) $row->language : '';
                $out['language'] = $language === '' ? null : $language;
            }

            if ($query->groupBy === 'subject_id') {
                $subjectType = isset($row->subject_type) ? (string) $row->subject_type : '';
                $subjectId = (int) ($row->subject_id ?? 0);
                $out['subject_type'] = $subjectType === '' ? null : $subjectType;
                $out['subject_id'] = $subjectId === 0 ? null : $subjectId;
            }

            return $out;
        })->all();
    }
}

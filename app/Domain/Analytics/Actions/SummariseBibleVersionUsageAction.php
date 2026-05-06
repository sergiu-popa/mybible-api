<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Actions;

use App\Domain\Analytics\DataTransferObjects\AnalyticsRangeQueryData;
use App\Domain\Analytics\Enums\EventType;
use Illuminate\Support\Facades\DB;

/**
 * Per-Bible-version event count of `bible.chapter.viewed` and
 * `bible.passage.viewed`, grouped by `metadata.version_abbreviation`.
 *
 * NOTE: this is the first known case of "metadata cut not in
 * rollup" — the rollup keys on (date, event_type, subject_type,
 * subject_id, language) and version is buried in JSON. We therefore
 * read the raw events table here. If the dashboard adds more
 * metadata cuts, promote a `metadata_key` / `metadata_value` pair to
 * the rollup schema in a follow-up story.
 */
final class SummariseBibleVersionUsageAction
{
    /**
     * @return array<int, array{version_abbreviation: string, count: int}>
     */
    public function execute(AnalyticsRangeQueryData $query): array
    {
        $rows = DB::table('analytics_events')
            ->whereIn('event_type', [
                EventType::BibleChapterViewed->value,
                EventType::BiblePassageViewed->value,
            ])
            ->whereBetween('occurred_at', [$query->from, $query->to])
            ->selectRaw("JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.version_abbreviation')) AS version_abbreviation, COUNT(*) AS count")
            ->groupBy('version_abbreviation')
            ->orderByDesc('count')
            ->get();

        return $rows
            ->filter(static fn ($r): bool => $r->version_abbreviation !== null)
            ->map(static fn ($r): array => [
                'version_abbreviation' => (string) $r->version_abbreviation,
                'count' => (int) $r->count,
            ])
            ->values()
            ->all();
    }
}

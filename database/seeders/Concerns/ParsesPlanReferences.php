<?php

declare(strict_types=1);

namespace Database\Seeders\Concerns;

use App\Domain\ReadingPlans\Enums\FragmentType;
use App\Domain\ReadingPlans\Models\ReadingPlanDay;
use App\Domain\ReadingPlans\Models\ReadingPlanDayFragment;

/**
 * Shared parsing for reading-plan day strings shipped by the seeders.
 *
 * Each day line is a comma-separated list of references (YouVersion-style
 * book identifiers, e.g. "GEN.1,MAT.2:1-12"). Multi-chapter ranges of the
 * same book like "GEN.1-3" are expanded into individual chapter references
 * so each one becomes its own tappable fragment in the UI.
 */
trait ParsesPlanReferences
{
    /**
     * Creates one References fragment per parsed reference, ordered as they
     * appear in the day line.
     */
    private function seedReferenceFragments(ReadingPlanDay $day, string $line): void
    {
        $position = 1;
        foreach (self::parseReferences($line) as $reference) {
            ReadingPlanDayFragment::query()->create([
                'reading_plan_day_id' => $day->id,
                'position' => $position++,
                'type' => FragmentType::References,
                'content' => [$reference],
            ]);
        }
    }

    /**
     * Splits a day line into one entry per chapter/passage.
     *
     * @return list<string>
     */
    private static function parseReferences(string $line): array
    {
        $references = [];

        foreach (explode(',', $line) as $entry) {
            $entry = trim($entry);
            if ($entry === '') {
                continue;
            }

            foreach (self::expandChapterRange($entry) as $reference) {
                $references[] = $reference;
            }
        }

        return $references;
    }

    /**
     * Expands a chapter-range reference like "GEN.1-3" into ["GEN.1", "GEN.2",
     * "GEN.3"]. Verse-scoped references (e.g. "MAT.2:1-12") and single-chapter
     * references (e.g. "GEN.1") are returned untouched.
     *
     * @return list<string>
     */
    private static function expandChapterRange(string $reference): array
    {
        if (! preg_match('/^([A-Z0-9]+)\.(\d+)-(\d+)$/', $reference, $matches)) {
            return [$reference];
        }

        [$book, $start, $end] = [$matches[1], (int) $matches[2], (int) $matches[3]];

        if ($end < $start) {
            return [$reference];
        }

        $expanded = [];
        for ($chapter = $start; $chapter <= $end; $chapter++) {
            $expanded[] = "{$book}.{$chapter}";
        }

        return $expanded;
    }
}

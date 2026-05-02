<?php

declare(strict_types=1);

namespace App\Domain\Bible\QueryBuilders;

use App\Domain\Bible\Exceptions\VerseRangeTooLargeException;
use App\Domain\Bible\Models\BibleBook;
use App\Domain\Bible\Models\BibleChapter;
use App\Domain\Bible\Models\BibleVerse;
use App\Domain\Bible\Models\BibleVersion;
use App\Domain\Reference\Reference;
use App\Domain\Reference\VerseRange;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends Builder<BibleVerse>
 */
final class BibleVerseQueryBuilder extends Builder
{
    public const VERSE_RANGE_CAP = 500;

    /**
     * Resolve a flat list of references against the `bible_verses` table.
     *
     * Plain {@see Reference} entries are grouped by (version, book, chapter)
     * and issued as one `WHERE verse IN (...)` query per group (or an
     * unconstrained chapter query when any contributing reference is a
     * whole-chapter lookup). {@see VerseRange} entries are issued as one
     * tuple-comparison query each, ordered by `(chapter, verse)`.
     *
     * The input is expected to already be version-resolved: every
     * {@see Reference}/{@see VerseRange} must carry a non-null version
     * abbreviation. The caller is responsible for the default-version
     * cascade.
     *
     * @param  array<int, Reference|VerseRange>  $references
     * @return Collection<int, BibleVerse>
     */
    public function lookupReferences(array $references): Collection
    {
        /** @var Collection<int, BibleVerse> $results */
        $results = new Collection;

        if ($references === []) {
            return $results;
        }

        [$plainReferences, $ranges] = $this->partition($references);

        $groups = $this->groupByVersionBookChapter($plainReferences);

        $versionAbbreviations = [];
        $bookAbbreviations = [];

        foreach ($groups as $group) {
            $versionAbbreviations[$group['version']] = true;
            $bookAbbreviations[$group['book']] = true;
        }

        foreach ($ranges as $range) {
            if ($range->version !== null) {
                $versionAbbreviations[$range->version] = true;
            }

            $bookAbbreviations[$range->book] = true;
        }

        if ($versionAbbreviations === [] && $bookAbbreviations === []) {
            return $results;
        }

        /** @var array<string, BibleVersion> $versionsByAbbreviation */
        $versionsByAbbreviation = BibleVersion::query()
            ->whereIn('abbreviation', array_keys($versionAbbreviations))
            ->get()
            ->keyBy('abbreviation')
            ->all();

        /** @var array<string, BibleBook> $booksByAbbreviation */
        $booksByAbbreviation = BibleBook::query()
            ->whereIn('abbreviation', array_keys($bookAbbreviations))
            ->get()
            ->keyBy('abbreviation')
            ->all();

        foreach ($groups as $group) {
            $version = $versionsByAbbreviation[$group['version']] ?? null;
            $book = $booksByAbbreviation[$group['book']] ?? null;

            if ($version === null || $book === null) {
                continue;
            }

            $query = BibleVerse::query()
                ->where('bible_version_id', $version->id)
                ->where('bible_book_id', $book->id)
                ->where('chapter', $group['chapter']);

            if (! $group['whole_chapter']) {
                $query->whereIn('verse', $group['verses']);
            }

            foreach ($query->get() as $verse) {
                $verse->setRelation('version', $version);
                $verse->setRelation('book', $book);
                $results->push($verse);
            }
        }

        foreach ($ranges as $range) {
            if ($range->version === null) {
                continue;
            }

            $version = $versionsByAbbreviation[$range->version] ?? null;
            $book = $booksByAbbreviation[$range->book] ?? null;

            if ($version === null || $book === null) {
                continue;
            }

            foreach ($this->lookupVerseRange($range, $version, $book) as $verse) {
                $results->push($verse);
            }
        }

        return $results;
    }

    /**
     * Resolve a single cross-chapter range using a tuple comparison.
     *
     * Throws {@see VerseRangeTooLargeException} when the expansion would
     * exceed {@see self::VERSE_RANGE_CAP}; this is the product floor that
     * prevents accidental whole-book pulls. The expansion is computed from
     * the seeded `bible_chapters.verse_count`; an unseeded book falls back
     * to "unbounded" (cap is not enforced) — see plan §9.
     *
     * @return Collection<int, BibleVerse>
     */
    public function lookupVerseRange(VerseRange $range, BibleVersion $version, BibleBook $book): Collection
    {
        $expanded = $this->expandedSize($range, $book);

        if ($expanded !== null && $expanded > self::VERSE_RANGE_CAP) {
            throw new VerseRangeTooLargeException($range, $expanded, self::VERSE_RANGE_CAP);
        }

        $verses = BibleVerse::query()
            ->where('bible_version_id', $version->id)
            ->where('bible_book_id', $book->id)
            ->where(function (Builder $query) use ($range): void {
                $query
                    ->where(function (Builder $inner) use ($range): void {
                        $inner
                            ->where('chapter', $range->startChapter)
                            ->where('verse', '>=', $range->startVerse);
                    })
                    ->orWhere(function (Builder $inner) use ($range): void {
                        $inner
                            ->where('chapter', '>', $range->startChapter)
                            ->where('chapter', '<', $range->endChapter);
                    })
                    ->orWhere(function (Builder $inner) use ($range): void {
                        $inner
                            ->where('chapter', $range->endChapter)
                            ->where('verse', '<=', $range->endVerse);
                    });
            })
            ->orderBy('chapter')
            ->orderBy('verse')
            ->get();

        /** @var Collection<int, BibleVerse> $verses */
        foreach ($verses as $verse) {
            $verse->setRelation('version', $version);
            $verse->setRelation('book', $book);
        }

        return $verses;
    }

    /**
     * @param  array<int, Reference|VerseRange>  $references
     * @return array{0: array<int, Reference>, 1: array<int, VerseRange>}
     */
    private function partition(array $references): array
    {
        $plain = [];
        $ranges = [];

        foreach ($references as $reference) {
            if ($reference instanceof VerseRange) {
                $ranges[] = $reference;

                continue;
            }

            $plain[] = $reference;
        }

        return [$plain, $ranges];
    }

    /**
     * Sum verse counts across the chapters the range covers, so the caller
     * can short-circuit before issuing a SELECT that scans tens of
     * thousands of rows.
     */
    private function expandedSize(VerseRange $range, BibleBook $book): ?int
    {
        $chapters = BibleChapter::query()
            ->where('bible_book_id', $book->id)
            ->whereBetween('number', [$range->startChapter, $range->endChapter])
            ->get()
            ->keyBy('number');

        $expectedChapters = $range->endChapter - $range->startChapter + 1;

        if ($chapters->count() < $expectedChapters) {
            return null;
        }

        $total = 0;

        for ($chapter = $range->startChapter; $chapter <= $range->endChapter; $chapter++) {
            /** @var BibleChapter|null $row */
            $row = $chapters->get($chapter);

            if ($row === null || $row->verse_count < 1) {
                return null;
            }

            if ($chapter === $range->startChapter && $chapter === $range->endChapter) {
                $total += max(0, $range->endVerse - $range->startVerse + 1);

                continue;
            }

            if ($chapter === $range->startChapter) {
                $total += max(0, $row->verse_count - $range->startVerse + 1);

                continue;
            }

            if ($chapter === $range->endChapter) {
                $total += min($row->verse_count, $range->endVerse);

                continue;
            }

            $total += $row->verse_count;
        }

        return $total;
    }

    /**
     * @param  array<int, Reference>  $references
     * @return array<string, array{version: string, book: string, chapter: int, verses: array<int, int>, whole_chapter: bool}>
     */
    private function groupByVersionBookChapter(array $references): array
    {
        $groups = [];

        foreach ($references as $reference) {
            if ($reference->version === null) {
                continue;
            }

            $key = sprintf('%s|%s|%d', $reference->version, $reference->book, $reference->chapter);

            if (! isset($groups[$key])) {
                $groups[$key] = [
                    'version' => $reference->version,
                    'book' => $reference->book,
                    'chapter' => $reference->chapter,
                    'verses' => [],
                    'whole_chapter' => false,
                ];
            }

            if ($reference->isWholeChapter()) {
                $groups[$key]['whole_chapter'] = true;
                $groups[$key]['verses'] = [];

                continue;
            }

            if ($groups[$key]['whole_chapter']) {
                continue;
            }

            $groups[$key]['verses'] = array_values(array_unique(array_merge($groups[$key]['verses'], $reference->verses)));
            sort($groups[$key]['verses']);
        }

        return $groups;
    }
}

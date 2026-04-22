<?php

declare(strict_types=1);

namespace App\Domain\Bible\QueryBuilders;

use App\Domain\Bible\Models\BibleBook;
use App\Domain\Bible\Models\BibleVerse;
use App\Domain\Bible\Models\BibleVersion;
use App\Domain\Reference\Reference;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends Builder<BibleVerse>
 */
final class BibleVerseQueryBuilder extends Builder
{
    /**
     * Resolve a flat list of references against the `bible_verses` table.
     *
     * References are grouped by (version, book, chapter) and issued as a
     * single `WHERE verse IN (...)` query per group (or an unconstrained
     * chapter query when any contributing reference is a whole-chapter
     * lookup).
     *
     * The input is expected to already be version-resolved: every
     * {@see Reference} must carry a non-null version abbreviation. The
     * caller is responsible for the default-version cascade.
     *
     * @param  array<int, Reference>  $references
     * @return Collection<int, BibleVerse>
     */
    public function lookupReferences(array $references): Collection
    {
        /** @var Collection<int, BibleVerse> $results */
        $results = new Collection;

        if ($references === []) {
            return $results;
        }

        $groups = $this->groupByVersionBookChapter($references);

        $versionAbbreviations = [];
        $bookAbbreviations = [];

        foreach ($groups as $group) {
            $versionAbbreviations[$group['version']] = true;
            $bookAbbreviations[$group['book']] = true;
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

        return $results;
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

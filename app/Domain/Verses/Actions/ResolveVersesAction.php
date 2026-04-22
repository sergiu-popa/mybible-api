<?php

declare(strict_types=1);

namespace App\Domain\Verses\Actions;

use App\Domain\Bible\Models\BibleBook;
use App\Domain\Bible\Models\BibleVerse;
use App\Domain\Reference\Reference;
use App\Domain\Verses\DataTransferObjects\ResolveVersesData;
use App\Domain\Verses\DataTransferObjects\VerseLookupResult;
use Illuminate\Database\Eloquent\Collection;

final class ResolveVersesAction
{
    public function handle(ResolveVersesData $data): VerseLookupResult
    {
        /** @var Collection<int, BibleVerse> $verses */
        $verses = BibleVerse::query()->lookupReferences($data->references);

        $verses = $verses->load(['version', 'book']);

        $missing = $this->computeMissing($data->references, $verses);

        return new VerseLookupResult(
            verses: $verses,
            missing: $missing,
        );
    }

    /**
     * @param  array<int, Reference>  $references
     * @param  Collection<int, BibleVerse>  $verses
     * @return array<int, array{version: string, book: string, chapter: int, verse: int}>
     */
    private function computeMissing(array $references, Collection $verses): array
    {
        $expected = $this->expectedTuples($references);

        if ($expected === []) {
            return [];
        }

        $resolved = [];

        foreach ($verses as $verse) {
            $key = sprintf(
                '%s|%s|%d|%d',
                $verse->version->abbreviation,
                $verse->book->abbreviation,
                $verse->chapter,
                $verse->verse,
            );
            $resolved[$key] = true;
        }

        $missing = [];

        foreach ($expected as $tuple) {
            $key = sprintf('%s|%s|%d|%d', $tuple['version'], $tuple['book'], $tuple['chapter'], $tuple['verse']);

            if (! isset($resolved[$key])) {
                $missing[] = $tuple;
            }
        }

        return $missing;
    }

    /**
     * @param  array<int, Reference>  $references
     * @return array<int, array{version: string, book: string, chapter: int, verse: int}>
     */
    private function expectedTuples(array $references): array
    {
        $seen = [];
        $tuples = [];

        foreach ($references as $reference) {
            if ($reference->version === null) {
                continue;
            }

            $verses = $reference->isWholeChapter()
                ? $this->wholeChapterVerseNumbers($reference)
                : $reference->verses;

            foreach ($verses as $verse) {
                $key = sprintf('%s|%s|%d|%d', $reference->version, $reference->book, $reference->chapter, $verse);

                if (isset($seen[$key])) {
                    continue;
                }

                $seen[$key] = true;
                $tuples[] = [
                    'version' => $reference->version,
                    'book' => $reference->book,
                    'chapter' => $reference->chapter,
                    'verse' => $verse,
                ];
            }
        }

        return $tuples;
    }

    /**
     * Whole-chapter references do not carry verse numbers. For the
     * missing-set computation we expand them using the seeded verse count
     * on the `bible_chapters` table so that gaps in the text table surface
     * in `meta.missing`. When the chapter row isn't present (unseeded
     * dataset) we fall back to an empty expansion — partial resolution
     * then reports "everything resolved" rather than inventing gaps.
     *
     * @return array<int, int>
     */
    private function wholeChapterVerseNumbers(Reference $reference): array
    {
        static $cache = [];

        $cacheKey = sprintf('%s|%d', $reference->book, $reference->chapter);

        if (isset($cache[$cacheKey])) {
            return $cache[$cacheKey];
        }

        $book = BibleBook::query()->where('abbreviation', $reference->book)->first();

        if ($book === null) {
            return $cache[$cacheKey] = [];
        }

        $chapter = $book->chapters()->where('number', $reference->chapter)->first();

        if ($chapter === null || $chapter->verse_count < 1) {
            return $cache[$cacheKey] = [];
        }

        return $cache[$cacheKey] = range(1, $chapter->verse_count);
    }
}

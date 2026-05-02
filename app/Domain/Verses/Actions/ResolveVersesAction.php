<?php

declare(strict_types=1);

namespace App\Domain\Verses\Actions;

use App\Domain\Bible\Models\BibleBook;
use App\Domain\Bible\Models\BibleVerse;
use App\Domain\Reference\Reference;
use App\Domain\Reference\VerseRange;
use App\Domain\Verses\DataTransferObjects\ResolveVersesData;
use App\Domain\Verses\DataTransferObjects\VerseLookupResult;
use Illuminate\Database\Eloquent\Collection;
use LogicException;

final class ResolveVersesAction
{
    /** @var array<string, int> */
    private array $chapterVerseCountCache = [];

    public function handle(ResolveVersesData $data): VerseLookupResult
    {
        /** @var Collection<int, BibleVerse> $verses */
        $verses = BibleVerse::query()->lookupReferences($data->references);

        $missing = $this->computeMissing($data->references, $verses);

        return new VerseLookupResult(
            verses: $verses,
            missing: $missing,
        );
    }

    /**
     * @param  array<int, Reference|VerseRange>  $references
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
     * @param  array<int, Reference|VerseRange>  $references
     * @return array<int, array{version: string, book: string, chapter: int, verse: int}>
     */
    private function expectedTuples(array $references): array
    {
        $seen = [];
        $tuples = [];

        foreach ($references as $reference) {
            if ($reference instanceof VerseRange && $reference->version === null) {
                throw new LogicException(
                    'VerseRange entered ResolveVersesAction without a resolved version; '
                    . 'normalize the version in the request layer before dispatching.',
                );
            }

            if ($reference->version === null) {
                continue;
            }

            $entries = $reference instanceof VerseRange
                ? $this->expandRange($reference)
                : $this->expandReference($reference);

            foreach ($entries as $entry) {
                $key = sprintf('%s|%s|%d|%d', $entry['version'], $entry['book'], $entry['chapter'], $entry['verse']);

                if (isset($seen[$key])) {
                    continue;
                }

                $seen[$key] = true;
                $tuples[] = $entry;
            }
        }

        return $tuples;
    }

    /**
     * @return array<int, array{version: string, book: string, chapter: int, verse: int}>
     */
    private function expandReference(Reference $reference): array
    {
        if ($reference->version === null) {
            return [];
        }

        $verses = $reference->isWholeChapter()
            ? $this->wholeChapterVerseNumbers($reference->book, $reference->chapter)
            : $reference->verses;

        return array_map(static fn (int $verse): array => [
            'version' => $reference->version,
            'book' => $reference->book,
            'chapter' => $reference->chapter,
            'verse' => $verse,
        ], $verses);
    }

    /**
     * @return array<int, array{version: string, book: string, chapter: int, verse: int}>
     */
    private function expandRange(VerseRange $range): array
    {
        if ($range->version === null) {
            throw new LogicException(
                'VerseRange entered ResolveVersesAction without a resolved version; '
                . 'normalize the version in the request layer before dispatching.',
            );
        }

        $tuples = [];

        for ($chapter = $range->startChapter; $chapter <= $range->endChapter; $chapter++) {
            $verseStart = $chapter === $range->startChapter ? $range->startVerse : 1;

            $verseEnd = $chapter === $range->endChapter
                ? $range->endVerse
                : $this->chapterVerseCount($range->book, $chapter);

            if ($verseEnd === 0 || $verseEnd < $verseStart) {
                continue;
            }

            for ($verse = $verseStart; $verse <= $verseEnd; $verse++) {
                $tuples[] = [
                    'version' => $range->version,
                    'book' => $range->book,
                    'chapter' => $chapter,
                    'verse' => $verse,
                ];
            }
        }

        return $tuples;
    }

    /**
     * @return array<int, int>
     */
    private function wholeChapterVerseNumbers(string $book, int $chapter): array
    {
        $count = $this->chapterVerseCount($book, $chapter);

        return $count === 0 ? [] : range(1, $count);
    }

    private function chapterVerseCount(string $bookAbbreviation, int $chapter): int
    {
        $cacheKey = sprintf('%s|%d', $bookAbbreviation, $chapter);

        if (array_key_exists($cacheKey, $this->chapterVerseCountCache)) {
            return $this->chapterVerseCountCache[$cacheKey];
        }

        $book = BibleBook::query()->where('abbreviation', $bookAbbreviation)->first();

        if ($book === null) {
            return $this->chapterVerseCountCache[$cacheKey] = 0;
        }

        $row = $book->chapters()->where('number', $chapter)->first();

        if ($row === null || $row->verse_count < 1) {
            return $this->chapterVerseCountCache[$cacheKey] = 0;
        }

        return $this->chapterVerseCountCache[$cacheKey] = $row->verse_count;
    }
}

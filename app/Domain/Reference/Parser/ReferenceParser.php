<?php

declare(strict_types=1);

namespace App\Domain\Reference\Parser;

use App\Domain\Reference\Data\BibleBookCatalog;
use App\Domain\Reference\Exceptions\InvalidReferenceException;
use App\Domain\Reference\Reference;

final class ReferenceParser
{
    public function __construct(
        private readonly ChapterRangeParser $chapterRangeParser = new ChapterRangeParser,
        private readonly MultipleReferenceParser $multipleReferenceParser = new MultipleReferenceParser,
    ) {}

    /**
     * Parse a canonical query into one or more {@see Reference} objects.
     *
     * @return array<int, Reference>
     */
    public function parse(string $query): array
    {
        if ($this->isMultipleReferences($query)) {
            return array_map(
                fn (string $sub): Reference => $this->parseOne($sub),
                $this->multipleReferenceParser->expand($query),
            );
        }

        if ($this->isChapterRange($query)) {
            return array_map(
                fn (string $sub): Reference => $this->parseOne($sub),
                $this->chapterRangeParser->expand($query),
            );
        }

        return [$this->parseOne($query)];
    }

    /**
     * Parse a canonical single-reference query like `GEN.1:1-3,5.VDC`.
     */
    public function parseOne(string $query): Reference
    {
        if (str_contains($query, ';')) {
            throw InvalidReferenceException::unparseable($query, 'multi-reference query passed to parseOne');
        }

        $parts = explode('.', $query);

        if (count($parts) !== 3) {
            throw InvalidReferenceException::unparseable($query, 'expected three dot-separated segments');
        }

        [$book, $passage, $version] = $parts;

        if (! BibleBookCatalog::hasBook($book)) {
            throw InvalidReferenceException::unknownBook($query, $book);
        }

        if ($passage === '') {
            throw InvalidReferenceException::unparseable($query, 'missing chapter');
        }

        if (str_contains($passage, ':')) {
            $passageParts = explode(':', $passage);

            if (count($passageParts) !== 2 || $passageParts[0] === '' || $passageParts[1] === '') {
                throw InvalidReferenceException::unparseable($query, 'malformed passage segment');
            }

            [$chapterPart, $versesPart] = $passageParts;
            $chapter = $this->parseChapter($query, $book, $chapterPart);
            $verses = $this->parseVerses($query, $versesPart);

            return new Reference($book, $chapter, $verses, $version === '' ? null : $version);
        }

        if (str_contains($passage, '-')) {
            throw InvalidReferenceException::unparseable($query, 'chapter range passed to parseOne');
        }

        $chapter = $this->parseChapter($query, $book, $passage);

        return new Reference($book, $chapter, [], $version === '' ? null : $version);
    }

    private function isMultipleReferences(string $query): bool
    {
        return str_contains($query, ';');
    }

    private function isChapterRange(string $query): bool
    {
        $parts = explode('.', $query);

        if (count($parts) !== 3) {
            return false;
        }

        $passage = $parts[1];

        return str_contains($passage, '-') && ! str_contains($passage, ':');
    }

    private function parseChapter(string $query, string $book, string $chapterPart): int
    {
        if ($chapterPart === '' || ! ctype_digit($chapterPart)) {
            throw InvalidReferenceException::unparseable($query, 'chapter must be a positive integer');
        }

        $chapter = (int) $chapterPart;
        $max = BibleBookCatalog::maxChapter($book);

        if ($chapter < 1 || $chapter > $max) {
            throw InvalidReferenceException::chapterOutOfRange($query, $book, $chapter, $max);
        }

        return $chapter;
    }

    /**
     * @return array<int, int>
     */
    private function parseVerses(string $query, string $versesPart): array
    {
        $verses = [];

        foreach (explode(',', $versesPart) as $segment) {
            if ($segment === '') {
                throw InvalidReferenceException::unparseable($query, 'empty verse segment');
            }

            if (str_contains($segment, '-')) {
                $bounds = explode('-', $segment);

                if (count($bounds) !== 2 || ! ctype_digit($bounds[0]) || ! ctype_digit($bounds[1])) {
                    throw InvalidReferenceException::unparseable($query, 'malformed verse range');
                }

                $start = (int) $bounds[0];
                $end = (int) $bounds[1];

                if ($start < 1 || $end < $start) {
                    throw InvalidReferenceException::unparseable($query, 'verse range bounds are invalid');
                }

                foreach (range($start, $end) as $verse) {
                    $verses[] = $verse;
                }

                continue;
            }

            if (! ctype_digit($segment)) {
                throw InvalidReferenceException::unparseable($query, 'verse must be a positive integer');
            }

            $verses[] = (int) $segment;
        }

        $verses = array_values(array_unique($verses));
        sort($verses);

        return $verses;
    }
}

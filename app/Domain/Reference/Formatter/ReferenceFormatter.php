<?php

declare(strict_types=1);

namespace App\Domain\Reference\Formatter;

use App\Domain\Reference\Exceptions\InvalidReferenceException;
use App\Domain\Reference\Formatter\Languages\EnglishFormatter;
use App\Domain\Reference\Formatter\Languages\HungarianFormatter;
use App\Domain\Reference\Formatter\Languages\LanguageFormatter;
use App\Domain\Reference\Formatter\Languages\RomanianFormatter;
use App\Domain\Reference\Reference;

final class ReferenceFormatter
{
    public function toCanonical(Reference $ref): string
    {
        if ($ref->version === null) {
            throw InvalidReferenceException::unparseable(
                sprintf('%s.%d', $ref->book, $ref->chapter),
                'cannot render canonical form without a version',
            );
        }

        if ($ref->isWholeChapter()) {
            return sprintf('%s.%d.%s', $ref->book, $ref->chapter, $ref->version);
        }

        return sprintf(
            '%s.%d:%s.%s',
            $ref->book,
            $ref->chapter,
            $this->collapseVerses($ref->verses),
            $ref->version,
        );
    }

    public function toHumanReadable(Reference $ref, string $language): string
    {
        $formatter = $this->resolveLanguage($language);

        $book = $formatter->bookName($ref->book);

        if ($ref->isWholeChapter()) {
            return sprintf('%s %d', $book, $ref->chapter);
        }

        return sprintf('%s %d:%s', $book, $ref->chapter, $this->collapseVerses($ref->verses));
    }

    public function forLanguage(string $language): LanguageFormatter
    {
        return $this->resolveLanguage($language);
    }

    /**
     * Collapse a sorted, unique list of verses into a comma-separated form
     * that re-introduces ranges where consecutive runs exist.
     *
     * Example: `[1,2,3,5,7,8,9]` → `"1-3,5,7-9"`.
     *
     * @param  array<int, int>  $verses
     */
    private function collapseVerses(array $verses): string
    {
        $segments = [];
        $start = null;
        $previous = null;

        foreach ($verses as $verse) {
            if ($start === null) {
                $start = $verse;
                $previous = $verse;

                continue;
            }

            if ($verse === $previous + 1) {
                $previous = $verse;

                continue;
            }

            $segments[] = $start === $previous
                ? (string) $start
                : sprintf('%d-%d', $start, $previous);

            $start = $verse;
            $previous = $verse;
        }

        if ($start !== null) {
            $segments[] = $start === $previous
                ? (string) $start
                : sprintf('%d-%d', $start, $previous);
        }

        return implode(',', $segments);
    }

    private function resolveLanguage(string $language): LanguageFormatter
    {
        return match ($language) {
            'ro' => new RomanianFormatter,
            'hu' => new HungarianFormatter,
            default => new EnglishFormatter,
        };
    }
}

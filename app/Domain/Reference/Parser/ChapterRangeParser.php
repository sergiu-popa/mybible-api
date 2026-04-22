<?php

declare(strict_types=1);

namespace App\Domain\Reference\Parser;

use App\Domain\Reference\Exceptions\InvalidReferenceException;

final class ChapterRangeParser
{
    /**
     * Expand a chapter-range query like `GEN.1-3.VDC` into the canonical
     * single-reference sub-queries `['GEN.1.VDC', 'GEN.2.VDC', 'GEN.3.VDC']`.
     *
     * @return array<int, string>
     */
    public function expand(string $query): array
    {
        $parts = explode('.', $query);

        if (count($parts) !== 3) {
            throw InvalidReferenceException::unparseable($query, 'expected three dot-separated segments');
        }

        [$book, $chapters, $version] = $parts;

        if (! str_contains($chapters, '-')) {
            throw InvalidReferenceException::unparseable($query, 'expected a chapter range "start-end"');
        }

        $bounds = explode('-', $chapters);

        if (count($bounds) !== 2 || $bounds[0] === '' || $bounds[1] === '') {
            throw InvalidReferenceException::unparseable($query, 'expected a chapter range "start-end"');
        }

        if (! ctype_digit($bounds[0]) || ! ctype_digit($bounds[1])) {
            throw InvalidReferenceException::unparseable($query, 'chapter range bounds must be positive integers');
        }

        $start = (int) $bounds[0];
        $end = (int) $bounds[1];

        if ($start < 1 || $end < $start) {
            throw InvalidReferenceException::unparseable($query, 'chapter range bounds are invalid');
        }

        $queries = [];

        foreach (range($start, $end) as $chapter) {
            $queries[] = sprintf('%s.%d.%s', $book, $chapter, $version);
        }

        return $queries;
    }
}

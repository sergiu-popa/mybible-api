<?php

declare(strict_types=1);

namespace App\Domain\Reference\Parser;

use App\Domain\Reference\Exceptions\InvalidReferenceException;

final class MultipleReferenceParser
{
    /**
     * Expand a multi-reference query like `GEN.1:1;2;3:5-7.VDC` into the
     * canonical single-reference sub-queries
     * `['GEN.1:1.VDC', 'GEN.2.VDC', 'GEN.3:5-7.VDC']`.
     *
     * @return array<int, string>
     */
    public function expand(string $query): array
    {
        $parts = explode('.', $query);

        if (count($parts) !== 3) {
            throw InvalidReferenceException::unparseable($query, 'expected three dot-separated segments');
        }

        [$book, $collection, $version] = $parts;

        if (! str_contains($collection, ';')) {
            throw InvalidReferenceException::unparseable($query, 'expected at least one ";" in the passage segment');
        }

        $queries = [];

        foreach (explode(';', $collection) as $segment) {
            if ($segment === '') {
                throw InvalidReferenceException::unparseable($query, 'empty sub-reference in multi-reference query');
            }

            $queries[] = sprintf('%s.%s.%s', $book, $segment, $version);
        }

        return $queries;
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Reference;

use App\Domain\Reference\Exceptions\InvalidReferenceException;

/**
 * Narrow value object for a chapter range expressed as a single
 * URL/path segment: `"5"` or `"1-3"`.
 *
 * The existing {@see Parser\ChapterRangeParser} operates on the canonical
 * `BOOK.range.VERSION` triple. This VO covers the simpler case where a
 * range appears on its own (e.g. an olympiad theme path segment), while
 * deferring to the same `InvalidReferenceException` surface so downstream
 * handlers treat both sources uniformly.
 */
final readonly class ChapterRange
{
    public function __construct(
        public int $from,
        public int $to,
    ) {}

    public static function fromSegment(string $segment): self
    {
        if ($segment === '') {
            throw InvalidReferenceException::unparseable($segment, 'chapter range segment is empty');
        }

        if (! str_contains($segment, '-')) {
            if (! ctype_digit($segment)) {
                throw InvalidReferenceException::unparseable(
                    $segment,
                    'chapter range bounds must be positive integers',
                );
            }

            $chapter = (int) $segment;

            if ($chapter < 1) {
                throw InvalidReferenceException::unparseable($segment, 'chapter range bounds are invalid');
            }

            return new self($chapter, $chapter);
        }

        $bounds = explode('-', $segment);

        if (count($bounds) !== 2 || $bounds[0] === '' || $bounds[1] === '') {
            throw InvalidReferenceException::unparseable($segment, 'expected a chapter range "start-end"');
        }

        if (! ctype_digit($bounds[0]) || ! ctype_digit($bounds[1])) {
            throw InvalidReferenceException::unparseable(
                $segment,
                'chapter range bounds must be positive integers',
            );
        }

        $from = (int) $bounds[0];
        $to = (int) $bounds[1];

        if ($from < 1 || $to < $from) {
            throw InvalidReferenceException::unparseable($segment, 'chapter range bounds are invalid');
        }

        return new self($from, $to);
    }

    public function isSingleChapter(): bool
    {
        return $this->from === $this->to;
    }

    public function toCanonicalSegment(): string
    {
        return $this->isSingleChapter()
            ? (string) $this->from
            : sprintf('%d-%d', $this->from, $this->to);
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Bible\Exceptions;

use App\Domain\Reference\VerseRange;
use RuntimeException;

/**
 * Thrown when a {@see VerseRange} expansion would exceed the safety cap
 * (default: 500 verses) so callers can return a 422 instead of issuing
 * an unbounded SELECT.
 */
final class VerseRangeTooLargeException extends RuntimeException
{
    public function __construct(
        public readonly VerseRange $range,
        public readonly int $expandedSize,
        public readonly int $cap,
    ) {
        parent::__construct(sprintf(
            'Verse range "%s" expands to %d verses (cap: %d).',
            $range->canonical(),
            $expandedSize,
            $cap,
        ));
    }
}

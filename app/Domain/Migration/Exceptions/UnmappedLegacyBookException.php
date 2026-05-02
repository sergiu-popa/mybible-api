<?php

declare(strict_types=1);

namespace App\Domain\Migration\Exceptions;

use App\Domain\Migration\Actions\BackfillLegacyBookAbbreviationsAction;
use RuntimeException;

/**
 * Raised by {@see BackfillLegacyBookAbbreviationsAction}
 * when a row carries a legacy book name that the operator has not yet
 * mapped to USFM-3. The migration aborts so cutover does not proceed
 * with corrupted identifiers; operator extends `_legacy_book_abbreviation_map`
 * and re-runs.
 */
final class UnmappedLegacyBookException extends RuntimeException
{
    public function __construct(
        public readonly string $table,
        public readonly string $column,
        public readonly int|string $rowId,
        public readonly string $value,
    ) {
        parent::__construct(sprintf(
            'Unmapped legacy book value "%s" in %s.%s (row id=%s). Extend _legacy_book_abbreviation_map and re-run.',
            $value,
            $table,
            $column,
            (string) $rowId,
        ));
    }
}

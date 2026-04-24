<?php

declare(strict_types=1);

namespace App\Domain\SabbathSchool\Exceptions;

use App\Domain\Reference\Exceptions\InvalidReferenceException;
use RuntimeException;

/**
 * Raised when a highlight's `passage` fails to parse via the reference parser.
 *
 * Wraps {@see InvalidReferenceException} so the HTTP handler can render `422`
 * for this domain-specific surface without re-exposing reference-domain
 * internals to the Sabbath School controller code path.
 */
final class InvalidSabbathSchoolPassageException extends RuntimeException
{
    public function __construct(
        public readonly string $passage,
        public readonly string $reason,
        ?InvalidReferenceException $previous = null,
    ) {
        parent::__construct(
            sprintf('Sabbath School passage "%s" is invalid: %s', $passage, $reason),
            previous: $previous,
        );
    }

    public static function fromReferenceException(string $passage, InvalidReferenceException $e): self
    {
        return new self($passage, $e->reason(), $e);
    }
}

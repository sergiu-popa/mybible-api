<?php

declare(strict_types=1);

namespace App\Domain\Commentary\Exceptions;

use RuntimeException;

/**
 * Thrown when an AI pass that depends on a previous pass runs against a
 * row that has not been corrected yet (e.g. AddReferences on a row
 * whose `plain` is null). Mapped to a 422 by the exception handler.
 */
final class CommentaryTextNotCorrectedException extends RuntimeException
{
    public function __construct(
        public readonly int $commentaryTextId,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function for(int $commentaryTextId): self
    {
        return new self(
            $commentaryTextId,
            sprintf('CommentaryText #%d has not been AI-corrected yet.', $commentaryTextId),
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Commentary\Exceptions;

use RuntimeException;

/**
 * Thrown when translating a commentary that has rows missing their
 * `plain` column. Mapped to a 422 by the exception handler.
 */
final class CommentaryNotCorrectedException extends RuntimeException
{
    public function __construct(
        public readonly int $commentaryId,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function for(int $commentaryId): self
    {
        return new self(
            $commentaryId,
            sprintf(
                'Commentary #%d has rows that have not been AI-corrected yet; translate cannot run.',
                $commentaryId,
            ),
        );
    }
}

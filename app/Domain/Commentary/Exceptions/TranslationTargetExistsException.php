<?php

declare(strict_types=1);

namespace App\Domain\Commentary\Exceptions;

use RuntimeException;

/**
 * Thrown when a translate request targets a `(source_commentary_id,
 * language)` pair that already has a translation row and `overwrite`
 * is false. Mapped to a 409 by the exception handler.
 */
final class TranslationTargetExistsException extends RuntimeException
{
    public function __construct(
        public readonly int $sourceCommentaryId,
        public readonly string $targetLanguage,
        public readonly int $existingCommentaryId,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function for(int $sourceCommentaryId, string $targetLanguage, int $existingCommentaryId): self
    {
        return new self(
            $sourceCommentaryId,
            $targetLanguage,
            $existingCommentaryId,
            sprintf(
                'A "%s" translation of commentary #%d already exists (#%d). Re-run with overwrite=true to replace it.',
                $targetLanguage,
                $sourceCommentaryId,
                $existingCommentaryId,
            ),
        );
    }
}

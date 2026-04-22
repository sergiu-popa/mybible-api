<?php

declare(strict_types=1);

namespace App\Domain\Reference\Exceptions;

use RuntimeException;

final class InvalidReferenceException extends RuntimeException
{
    private function __construct(
        private readonly string $input,
        private readonly string $reason,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function unparseable(string $input, string $reason): self
    {
        return new self(
            $input,
            $reason,
            sprintf('Cannot parse reference "%s": %s', $input, $reason),
        );
    }

    public static function unknownBook(string $input, string $book): self
    {
        $reason = sprintf('unknown book "%s"', $book);

        return new self(
            $input,
            $reason,
            sprintf('Cannot parse reference "%s": %s', $input, $reason),
        );
    }

    public static function chapterOutOfRange(string $input, string $book, int $chapter, int $max): self
    {
        $reason = sprintf('chapter %d out of range for book "%s" (max %d)', $chapter, $book, $max);

        return new self(
            $input,
            $reason,
            sprintf('Cannot parse reference "%s": %s', $input, $reason),
        );
    }

    public function input(): string
    {
        return $this->input;
    }

    public function reason(): string
    {
        return $this->reason;
    }
}

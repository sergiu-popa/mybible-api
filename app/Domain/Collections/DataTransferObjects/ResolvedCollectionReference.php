<?php

declare(strict_types=1);

namespace App\Domain\Collections\DataTransferObjects;

final readonly class ResolvedCollectionReference
{
    /**
     * @param  ?array<int, array{book: string, chapter: int, verses: array<int, int>, version: ?string}>  $parsed
     */
    public function __construct(
        public string $raw,
        public ?array $parsed,
        public ?string $displayText,
        public ?string $parseError,
    ) {}
}

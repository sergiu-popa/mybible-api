<?php

declare(strict_types=1);

namespace App\Domain\Olympiad\DataTransferObjects;

use App\Domain\Shared\Enums\Language;

final readonly class ListOlympiadAttemptsFilter
{
    public function __construct(
        public ?Language $language,
        public ?string $book,
        public ?string $chaptersLabel,
        public ?int $userId,
    ) {}
}

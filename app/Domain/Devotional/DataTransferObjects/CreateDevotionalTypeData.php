<?php

declare(strict_types=1);

namespace App\Domain\Devotional\DataTransferObjects;

final readonly class CreateDevotionalTypeData
{
    public function __construct(
        public string $slug,
        public string $title,
        public int $position,
        public ?string $language,
    ) {}
}

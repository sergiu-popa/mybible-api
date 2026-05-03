<?php

declare(strict_types=1);

namespace App\Domain\Devotional\DataTransferObjects;

final readonly class UpdateDevotionalTypeData
{
    public function __construct(
        public ?string $slug,
        public ?string $title,
        public ?int $position,
        public ?string $language,
        public bool $slugProvided,
        public bool $titleProvided,
        public bool $positionProvided,
        public bool $languageProvided,
    ) {}
}

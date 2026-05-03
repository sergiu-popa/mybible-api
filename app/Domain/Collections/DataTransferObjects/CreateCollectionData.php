<?php

declare(strict_types=1);

namespace App\Domain\Collections\DataTransferObjects;

final readonly class CreateCollectionData
{
    public function __construct(
        public string $slug,
        public string $name,
        public string $language,
        public int $position,
    ) {}
}

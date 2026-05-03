<?php

declare(strict_types=1);

namespace App\Domain\Collections\DataTransferObjects;

final readonly class CreateCollectionTopicData
{
    public function __construct(
        public ?int $collectionId,
        public string $language,
        public string $name,
        public ?string $description,
        public ?string $imageCdnUrl,
        public int $position,
    ) {}
}

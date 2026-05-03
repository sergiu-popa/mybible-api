<?php

declare(strict_types=1);

namespace App\Domain\Collections\DataTransferObjects;

final readonly class UpdateCollectionTopicData
{
    public function __construct(
        public ?string $name,
        public ?string $description,
        public ?string $imageCdnUrl,
        public ?int $position,
        public bool $nameProvided,
        public bool $descriptionProvided,
        public bool $imageCdnUrlProvided,
        public bool $positionProvided,
    ) {}
}

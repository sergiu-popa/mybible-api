<?php

declare(strict_types=1);

namespace App\Domain\Analytics\DataTransferObjects;

final readonly class ResourceDownloadContextData
{
    public function __construct(
        public ?int $userId,
        public ?string $deviceId,
        public ?string $language,
        public ?string $source,
    ) {}
}

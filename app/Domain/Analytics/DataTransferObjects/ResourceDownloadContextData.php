<?php

declare(strict_types=1);

namespace App\Domain\Analytics\DataTransferObjects;

use App\Domain\Analytics\Enums\EventSource;

final readonly class ResourceDownloadContextData
{
    public function __construct(
        public ?int $userId,
        public ?string $deviceId,
        public ?string $language,
        public ?EventSource $source,
    ) {}
}

<?php

declare(strict_types=1);

namespace App\Domain\Mobile\DataTransferObjects;

use Carbon\CarbonImmutable;

final readonly class CreateMobileVersionData
{
    /**
     * @param  ?array<string, mixed>  $releaseNotes
     */
    public function __construct(
        public string $platform,
        public string $kind,
        public string $version,
        public ?CarbonImmutable $releasedAt,
        public ?array $releaseNotes,
        public ?string $storeUrl,
    ) {}
}

<?php

declare(strict_types=1);

namespace App\Domain\Mobile\DataTransferObjects;

use Carbon\CarbonImmutable;

final readonly class UpdateMobileVersionData
{
    /**
     * @param  ?array<string, mixed>  $releaseNotes
     */
    public function __construct(
        public ?string $platform,
        public ?string $kind,
        public ?string $version,
        public ?CarbonImmutable $releasedAt,
        public ?array $releaseNotes,
        public ?string $storeUrl,
        public bool $platformProvided,
        public bool $kindProvided,
        public bool $versionProvided,
        public bool $releasedAtProvided,
        public bool $releaseNotesProvided,
        public bool $storeUrlProvided,
    ) {}
}

<?php

declare(strict_types=1);

namespace App\Domain\Analytics\DataTransferObjects;

use Carbon\CarbonImmutable;

final readonly class SummaryQueryData
{
    public function __construct(
        public CarbonImmutable $from,
        public CarbonImmutable $to,
        public string $groupBy,
        public ?string $downloadableType,
        public ?string $language,
    ) {}
}

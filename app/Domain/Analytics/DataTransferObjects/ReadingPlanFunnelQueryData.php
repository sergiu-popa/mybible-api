<?php

declare(strict_types=1);

namespace App\Domain\Analytics\DataTransferObjects;

final readonly class ReadingPlanFunnelQueryData
{
    public function __construct(
        public AnalyticsRangeQueryData $range,
        public ?int $planId,
    ) {}
}

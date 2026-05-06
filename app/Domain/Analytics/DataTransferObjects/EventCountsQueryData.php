<?php

declare(strict_types=1);

namespace App\Domain\Analytics\DataTransferObjects;

use App\Domain\Analytics\Enums\EventType;

final readonly class EventCountsQueryData
{
    public function __construct(
        public AnalyticsRangeQueryData $range,
        public EventType $eventType,
        public ?string $groupBy,
    ) {}
}

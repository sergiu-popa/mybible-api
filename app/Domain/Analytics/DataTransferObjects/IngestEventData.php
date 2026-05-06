<?php

declare(strict_types=1);

namespace App\Domain\Analytics\DataTransferObjects;

use App\Domain\Analytics\Enums\EventType;
use Carbon\CarbonImmutable;

final readonly class IngestEventData
{
    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function __construct(
        public EventType $eventType,
        public ?string $subjectType,
        public ?int $subjectId,
        public ?string $language,
        public ?array $metadata,
        public CarbonImmutable $occurredAt,
    ) {}
}

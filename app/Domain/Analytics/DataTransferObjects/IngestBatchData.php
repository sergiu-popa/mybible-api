<?php

declare(strict_types=1);

namespace App\Domain\Analytics\DataTransferObjects;

final readonly class IngestBatchData
{
    /**
     * @param  array<int, IngestEventData>  $events
     */
    public function __construct(
        public array $events,
        public ResourceDownloadContextData $context,
        public ?string $appVersion,
    ) {}
}

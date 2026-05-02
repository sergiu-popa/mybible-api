<?php

declare(strict_types=1);

namespace App\Domain\Sync\DataTransferObjects;

use DateTimeImmutable;

final readonly class SyncTypeDelta
{
    /**
     * @param  array<int, mixed>  $upserted
     * @param  array<int, int>  $deleted
     */
    public function __construct(
        public array $upserted,
        public array $deleted,
        public ?DateTimeImmutable $maxSeenAt,
    ) {}
}

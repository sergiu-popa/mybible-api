<?php

declare(strict_types=1);

namespace App\Domain\Sync\Sync;

use App\Domain\Sync\DataTransferObjects\SyncTypeDelta;
use DateTimeImmutable;

interface SyncBuilder
{
    public function fetch(int $userId, DateTimeImmutable $since, int $cap): SyncTypeDelta;

    /** Response envelope key, e.g. "favorites", "notes". */
    public function key(): string;
}

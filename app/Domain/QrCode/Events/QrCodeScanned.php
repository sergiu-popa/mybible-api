<?php

declare(strict_types=1);

namespace App\Domain\QrCode\Events;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Events\Dispatchable;

final class QrCodeScanned
{
    use Dispatchable;

    public function __construct(
        public readonly int $qrCodeId,
        public readonly string $place,
        public readonly string $source,
        public readonly string $destination,
        public readonly CarbonImmutable $scannedAt,
    ) {}
}

<?php

declare(strict_types=1);

namespace App\Domain\QrCode\Actions;

use App\Domain\QrCode\Events\QrCodeScanned;
use App\Domain\QrCode\Models\QrCode;
use Carbon\CarbonImmutable;

final class RecordQrCodeScanAction
{
    public function handle(QrCode $qrCode): void
    {
        QrCodeScanned::dispatch(
            $qrCode->id,
            $qrCode->place,
            $qrCode->source,
            $qrCode->destination,
            CarbonImmutable::now(),
        );
    }
}

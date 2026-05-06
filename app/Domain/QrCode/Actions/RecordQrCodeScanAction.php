<?php

declare(strict_types=1);

namespace App\Domain\QrCode\Actions;

use App\Domain\Analytics\Actions\RecordAnalyticsEventAction;
use App\Domain\Analytics\DataTransferObjects\ResourceDownloadContextData;
use App\Domain\Analytics\Enums\EventType;
use App\Domain\QrCode\Events\QrCodeScanned;
use App\Domain\QrCode\Models\QrCode;
use Carbon\CarbonImmutable;

final class RecordQrCodeScanAction
{
    public function __construct(
        private readonly RecordAnalyticsEventAction $recordAnalyticsEvent,
    ) {}

    public function handle(QrCode $qrCode, ?ResourceDownloadContextData $context = null): void
    {
        QrCodeScanned::dispatch(
            $qrCode->id,
            $qrCode->place,
            $qrCode->source,
            $qrCode->destination,
            CarbonImmutable::now(),
        );

        $this->recordAnalyticsEvent->execute(
            eventType: EventType::QrCodeScanned,
            context: $context ?? new ResourceDownloadContextData(
                userId: null,
                deviceId: null,
                language: null,
                source: null,
            ),
            subject: $qrCode,
        );
    }
}

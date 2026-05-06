<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\QrCode;

use App\Domain\Analytics\Support\ClientContextResolver;
use App\Domain\QrCode\Actions\RecordQrCodeScanAction;
use App\Domain\QrCode\Models\QrCode;
use App\Http\Requests\QrCode\RecordQrCodeScanRequest;
use Illuminate\Http\Response;

final class RecordQrCodeScanController
{
    public function __invoke(
        RecordQrCodeScanRequest $request,
        QrCode $qr,
        RecordQrCodeScanAction $action,
    ): Response {
        $action->handle($qr, ClientContextResolver::fromRequest($request));

        return response()->noContent();
    }
}

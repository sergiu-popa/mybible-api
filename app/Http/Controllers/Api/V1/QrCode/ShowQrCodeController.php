<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\QrCode;

use App\Domain\QrCode\Models\QrCode;
use App\Domain\Reference\Formatter\ReferenceFormatter;
use App\Http\Requests\QrCode\ShowQrCodeRequest;
use App\Http\Resources\QrCode\QrCodeResource;

/**
 * @tags QR Codes
 */
final class ShowQrCodeController
{
    /**
     * Look up a QR code by canonical Bible reference.
     *
     * Returns the stored QR metadata (destination URL and image URL). Returns
     * 404 when no precomputed QR exists for the given reference.
     */
    public function __invoke(ShowQrCodeRequest $request, ReferenceFormatter $formatter): QrCodeResource
    {
        $canonical = $request->canonicalReference($formatter);

        $qrCode = QrCode::query()
            ->forReference($canonical)
            ->firstOrFail();

        return QrCodeResource::make($qrCode);
    }
}

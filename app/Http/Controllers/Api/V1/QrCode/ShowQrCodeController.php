<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\QrCode;

use App\Domain\QrCode\Actions\ShowQrCodeAction;
use App\Domain\Reference\Formatter\ReferenceFormatter;
use App\Http\Requests\QrCode\ShowQrCodeRequest;
use Illuminate\Http\JsonResponse;

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
    public function __invoke(
        ShowQrCodeRequest $request,
        ReferenceFormatter $formatter,
        ShowQrCodeAction $action,
    ): JsonResponse {
        $canonical = $request->canonicalReference($formatter);

        return response()->json($action->execute($canonical));
    }
}

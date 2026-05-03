<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\QrCode;

use App\Domain\QrCode\Actions\DeleteQrCodeAction;
use App\Domain\QrCode\Models\QrCode;
use App\Http\Requests\Admin\QrCode\DeleteQrCodeRequest;
use Illuminate\Http\Response;

final class DeleteQrCodeController
{
    public function __invoke(
        DeleteQrCodeRequest $request,
        QrCode $qr,
        DeleteQrCodeAction $action,
    ): Response {
        $action->handle($qr);

        return response()->noContent();
    }
}

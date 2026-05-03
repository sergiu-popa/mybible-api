<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\QrCode;

use App\Domain\QrCode\Actions\UpdateQrCodeAction;
use App\Domain\QrCode\Models\QrCode;
use App\Http\Requests\Admin\QrCode\UpdateQrCodeRequest;
use App\Http\Resources\QrCode\QrCodeResource;

final class UpdateQrCodeController
{
    public function __invoke(
        UpdateQrCodeRequest $request,
        QrCode $qr,
        UpdateQrCodeAction $action,
    ): QrCodeResource {
        return QrCodeResource::make($action->handle($qr, $request->toData()));
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\QrCode;

use App\Domain\QrCode\Actions\CreateQrCodeAction;
use App\Http\Requests\Admin\QrCode\CreateQrCodeRequest;
use App\Http\Resources\QrCode\QrCodeResource;
use Illuminate\Http\JsonResponse;

final class CreateQrCodeController
{
    public function __invoke(
        CreateQrCodeRequest $request,
        CreateQrCodeAction $action,
    ): JsonResponse {
        $qrCode = $action->handle($request->toData());

        return QrCodeResource::make($qrCode)
            ->response()
            ->setStatusCode(201);
    }
}

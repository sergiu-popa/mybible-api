<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\QrCode;

use App\Domain\QrCode\Actions\ListAdminQrCodesAction;
use App\Http\Requests\Admin\QrCode\ListAdminQrCodesRequest;
use App\Http\Resources\QrCode\QrCodeResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ListAdminQrCodesController
{
    public function __invoke(
        ListAdminQrCodesRequest $request,
        ListAdminQrCodesAction $action,
    ): AnonymousResourceCollection {
        return QrCodeResource::collection(
            $action->handle($request->pageNumber(), $request->perPage()),
        );
    }
}

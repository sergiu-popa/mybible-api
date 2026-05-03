<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Mobile;

use App\Domain\Mobile\Actions\CreateMobileVersionAction;
use App\Http\Requests\Admin\Mobile\CreateMobileVersionRequest;
use App\Http\Resources\Mobile\AdminMobileVersionResource;
use Illuminate\Http\JsonResponse;

final class CreateMobileVersionController
{
    public function __invoke(
        CreateMobileVersionRequest $request,
        CreateMobileVersionAction $action,
    ): JsonResponse {
        $version = $action->handle($request->toData());

        return AdminMobileVersionResource::make($version)
            ->response()
            ->setStatusCode(201);
    }
}

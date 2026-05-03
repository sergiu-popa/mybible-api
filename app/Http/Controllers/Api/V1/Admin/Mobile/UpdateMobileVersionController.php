<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Mobile;

use App\Domain\Mobile\Actions\UpdateMobileVersionAction;
use App\Domain\Mobile\Models\MobileVersion;
use App\Http\Requests\Admin\Mobile\UpdateMobileVersionRequest;
use App\Http\Resources\Mobile\AdminMobileVersionResource;

final class UpdateMobileVersionController
{
    public function __invoke(
        UpdateMobileVersionRequest $request,
        MobileVersion $version,
        UpdateMobileVersionAction $action,
    ): AdminMobileVersionResource {
        return AdminMobileVersionResource::make($action->handle($version, $request->toData()));
    }
}

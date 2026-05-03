<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Mobile;

use App\Domain\Mobile\Actions\DeleteMobileVersionAction;
use App\Domain\Mobile\Models\MobileVersion;
use App\Http\Requests\Admin\Mobile\DeleteMobileVersionRequest;
use Illuminate\Http\Response;

final class DeleteMobileVersionController
{
    public function __invoke(
        DeleteMobileVersionRequest $request,
        MobileVersion $version,
        DeleteMobileVersionAction $action,
    ): Response {
        $action->handle($version);

        return response()->noContent();
    }
}

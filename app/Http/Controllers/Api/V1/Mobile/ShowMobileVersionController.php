<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Mobile;

use App\Domain\Mobile\Actions\ShowMobileVersionAction;
use App\Http\Requests\Mobile\ShowMobileVersionRequest;
use App\Http\Resources\Mobile\MobileVersionResource;

/**
 * @tags Mobile
 */
final class ShowMobileVersionController
{
    /**
     * Mobile app version / update check.
     *
     * Returns per-platform minimum/latest versions and the store update URL
     * so mobile clients can decide whether to prompt or force-update. Backed
     * by the `mobile_versions` table; falls back to `config/mobile.php` for
     * fields not yet migrated.
     */
    public function __invoke(
        ShowMobileVersionRequest $request,
        ShowMobileVersionAction $action,
    ): MobileVersionResource {
        return MobileVersionResource::make($action->handle($request->platform()));
    }
}

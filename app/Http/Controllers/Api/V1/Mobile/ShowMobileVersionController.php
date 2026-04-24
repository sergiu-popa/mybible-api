<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Mobile;

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
     * by `config/mobile.php` — no database.
     */
    public function __invoke(ShowMobileVersionRequest $request): MobileVersionResource
    {
        $platform = $request->platform();

        /** @var array<string, mixed> $values */
        $values = config('mobile.' . $platform, []);

        return MobileVersionResource::make(['platform' => $platform] + $values);
    }
}

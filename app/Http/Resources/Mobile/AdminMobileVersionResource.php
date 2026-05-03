<?php

declare(strict_types=1);

namespace App\Http\Resources\Mobile;

use App\Domain\Mobile\Models\MobileVersion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Admin shape for `MobileVersion` rows — full DB row including `released_at`,
 * `release_notes`, `store_url`. Public `/mobile/version` uses
 * {@see MobileVersionResource} which keeps the locked mobile-client shape.
 *
 * @property MobileVersion $resource
 */
final class AdminMobileVersionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var MobileVersion $version */
        $version = $this->resource;

        return [
            'id' => $version->id,
            'platform' => $version->platform,
            'kind' => $version->kind,
            'version' => $version->version,
            'released_at' => $version->released_at?->toIso8601String(),
            'release_notes' => $version->release_notes,
            'store_url' => $version->store_url,
        ];
    }
}

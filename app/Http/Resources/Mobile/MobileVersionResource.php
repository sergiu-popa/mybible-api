<?php

declare(strict_types=1);

namespace App\Http\Resources\Mobile;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public `GET /api/v1/mobile/version` shape — locked by the mobile-client
 * contract. Receives the flat array payload from `MobileVersionsRepository`.
 *
 * Admin endpoints use {@see AdminMobileVersionResource}, which exposes the
 * full DB row.
 */
final class MobileVersionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $data */
        $data = (array) $this->resource;

        return [
            'platform' => $data['platform'] ?? null,
            'minimum_supported_version' => $data['minimum_supported_version'] ?? null,
            'latest_version' => $data['latest_version'] ?? null,
            'update_url' => $data['update_url'] ?? null,
            'force_update_below' => $data['force_update_below'] ?? null,
        ];
    }
}

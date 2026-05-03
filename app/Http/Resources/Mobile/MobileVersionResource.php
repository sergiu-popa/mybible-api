<?php

declare(strict_types=1);

namespace App\Http\Resources\Mobile;

use App\Domain\Mobile\Models\MobileVersion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Wraps per-platform mobile version metadata.
 *
 * Public `GET /mobile/version` passes a flat array (the repository payload)
 * keeping the legacy mobile-client contract shape; admin endpoints pass a
 * `MobileVersion` model and get the full DB row including the new
 * `released_at`, `release_notes`, `store_url` fields.
 */
final class MobileVersionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        if ($this->resource instanceof MobileVersion) {
            return [
                'id' => $this->resource->id,
                'platform' => $this->resource->platform,
                'kind' => $this->resource->kind,
                'version' => $this->resource->version,
                'released_at' => $this->resource->released_at?->toIso8601String(),
                'release_notes' => $this->resource->release_notes,
                'store_url' => $this->resource->store_url,
            ];
        }

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

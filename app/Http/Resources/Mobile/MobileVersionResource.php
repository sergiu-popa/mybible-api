<?php

declare(strict_types=1);

namespace App\Http\Resources\Mobile;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Wraps per-platform mobile version metadata from `config/mobile.php`.
 *
 * Field names on the payload are part of the mobile client contract and must
 * not be renamed — see MBA-019 plan. `resource` is an associative array keyed
 * by `platform` plus the `config('mobile.{platform}')` values.
 */
final class MobileVersionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $data */
        $data = $this->resource;

        return [
            'platform' => $data['platform'] ?? null,
            'minimum_supported_version' => $data['minimum_supported_version'] ?? null,
            'latest_version' => $data['latest_version'] ?? null,
            'update_url' => $data['update_url'] ?? null,
            'force_update_below' => $data['force_update_below'] ?? null,
        ];
    }
}

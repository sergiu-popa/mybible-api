<?php

declare(strict_types=1);

namespace App\Http\Resources\Analytics;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class ResourceDownloadSummaryRowResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $row */
        $row = (array) $this->resource;

        return [
            'date' => $row['date'] ?? null,
            'downloadable_type' => $row['downloadable_type'] ?? null,
            'downloadable_id' => $row['downloadable_id'] ?? null,
            'language' => $row['language'] ?? null,
            'count' => $row['count'] ?? 0,
            'unique_devices' => $row['unique_devices'] ?? 0,
        ];
    }
}

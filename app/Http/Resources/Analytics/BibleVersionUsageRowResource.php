<?php

declare(strict_types=1);

namespace App\Http\Resources\Analytics;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class BibleVersionUsageRowResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $row */
        $row = (array) $this->resource;

        return [
            'version_abbreviation' => $row['version_abbreviation'] ?? null,
            'count' => (int) ($row['count'] ?? 0),
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Resources\Analytics;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class ReadingPlanFunnelResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $row */
        $row = (array) $this->resource;

        return [
            'started' => (int) ($row['started'] ?? 0),
            'completed_per_day' => $row['completed_per_day'] ?? [],
            'abandoned' => (int) ($row['abandoned'] ?? 0),
            'abandoned_at_day' => $row['abandoned_at_day'] ?? [],
            'completed' => (int) ($row['completed'] ?? 0),
        ];
    }
}

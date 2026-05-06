<?php

declare(strict_types=1);

namespace App\Http\Resources\Analytics;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read array{
 *     total_events: int,
 *     dau: int,
 *     mau: int,
 *     top_event_types: array<int, array{event_type: string, count: int}>
 * } $resource
 */
final class AnalyticsSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $row */
        $row = (array) $this->resource;

        return [
            'total_events' => (int) ($row['total_events'] ?? 0),
            'dau' => (int) ($row['dau'] ?? 0),
            'mau' => (int) ($row['mau'] ?? 0),
            'top_event_types' => $row['top_event_types'] ?? [],
        ];
    }
}

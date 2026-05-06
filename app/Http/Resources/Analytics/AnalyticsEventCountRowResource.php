<?php

declare(strict_types=1);

namespace App\Http\Resources\Analytics;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class AnalyticsEventCountRowResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $row */
        $row = (array) $this->resource;

        // Sentinel translation (''/0 → null) is owned by ListEventCountsAction;
        // the resource is a flat passthrough.
        return $row;
    }
}

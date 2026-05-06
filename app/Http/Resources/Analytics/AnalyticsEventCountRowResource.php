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

        $out = [
            'date' => $row['date'] ?? null,
            'count' => (int) ($row['count'] ?? 0),
        ];

        if (array_key_exists('language', $row)) {
            $out['language'] = $row['language'];
        }

        if (array_key_exists('subject_type', $row)) {
            $out['subject_type'] = $row['subject_type'];
            $out['subject_id'] = $row['subject_id'] ?? null;
        }

        return $out;
    }
}

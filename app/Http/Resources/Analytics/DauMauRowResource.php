<?php

declare(strict_types=1);

namespace App\Http\Resources\Analytics;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class DauMauRowResource extends JsonResource
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
            'dau_users' => (int) ($row['dau_users'] ?? 0),
            'mau_users' => (int) ($row['mau_users'] ?? 0),
            'dau_devices' => (int) ($row['dau_devices'] ?? 0),
            'mau_devices' => (int) ($row['mau_devices'] ?? 0),
        ];
    }
}

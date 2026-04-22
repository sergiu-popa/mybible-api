<?php

declare(strict_types=1);

namespace App\Http\Resources\Devotionals;

use App\Domain\Devotional\Models\DevotionalFavorite;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin DevotionalFavorite
 */
final class DevotionalFavoriteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'created_at' => $this->created_at->toIso8601String(),
            'devotional' => DevotionalResource::make($this->whenLoaded('devotional')),
        ];
    }
}

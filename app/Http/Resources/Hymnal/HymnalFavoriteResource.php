<?php

declare(strict_types=1);

namespace App\Http\Resources\Hymnal;

use App\Domain\Hymnal\Models\HymnalFavorite;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin HymnalFavorite
 */
final class HymnalFavoriteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'created_at' => $this->created_at?->toIso8601String(),
            'song' => HymnalSongResource::make($this->whenLoaded('song')),
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Resources\Favorites;

use App\Domain\Favorites\Models\FavoriteCategory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin FavoriteCategory
 */
final class FavoriteCategoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'color' => $this->color,
            'favorites_count' => $this->whenCounted('favorites'),
        ];
    }
}

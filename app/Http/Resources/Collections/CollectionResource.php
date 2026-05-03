<?php

declare(strict_types=1);

namespace App\Http\Resources\Collections;

use App\Domain\Collections\Models\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Collection
 */
final class CollectionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'language' => $this->language,
            'position' => $this->position,
            'topics_count' => (int) ($this->topics_count ?? 0),
        ];
    }
}

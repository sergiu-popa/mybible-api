<?php

declare(strict_types=1);

namespace App\Http\Resources\Collections;

use App\Domain\Collections\Models\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Collection
 */
final class CollectionDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $topics = $this->resource->relationLoaded('topics')
            ? $this->resource->topics
            : $this->resource->topics()->get();

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'language' => $this->language,
            'position' => $this->position,
            'topics' => $topics->map(static fn ($topic): array => [
                'id' => $topic->id,
                'name' => $topic->name,
                'description' => $topic->description,
                'image_url' => $topic->image_cdn_url,
                'position' => $topic->position,
            ])->all(),
        ];
    }
}

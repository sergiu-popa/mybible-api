<?php

declare(strict_types=1);

namespace App\Http\Resources\EducationalResources;

use App\Domain\EducationalResources\Models\ResourceBook;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ResourceBook
 */
class ResourceBookListResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'name' => $this->name,
            'language' => $this->language,
            'description' => $this->description,
            'cover_image_url' => $this->cover_image_url,
            'author' => $this->author,
            'published_at' => $this->published_at?->toIso8601String(),
            'chapter_count' => $this->resolveChapterCount(),
        ];
    }

    private function resolveChapterCount(): int
    {
        // Detail callers eager-load `chapters` (not `withCount`); read off
        // the hydrated collection rather than firing a fresh COUNT(*).
        if ($this->resource->relationLoaded('chapters')) {
            return $this->chapters->count();
        }

        return (int) ($this->chapters_count ?? $this->chapters()->count());
    }
}

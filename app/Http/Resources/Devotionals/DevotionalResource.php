<?php

declare(strict_types=1);

namespace App\Http\Resources\Devotionals;

use App\Domain\Devotional\Models\Devotional;
use App\Domain\Devotional\Models\DevotionalType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Devotional
 */
final class DevotionalResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'date' => $this->date->format('Y-m-d'),
            'type' => $this->resolveTypeSlug(),
            'language' => $this->language,
            'title' => $this->title,
            'content' => $this->content,
            'audio_cdn_url' => $this->whenNotNull($this->audio_cdn_url),
            'audio_embed' => $this->whenNotNull($this->audio_embed),
            'video_embed' => $this->whenNotNull($this->video_embed),
            'passage' => $this->whenNotNull($this->passage),
            'author' => $this->whenNotNull($this->author),
        ];
    }

    private function resolveTypeSlug(): ?string
    {
        if ($this->resource->relationLoaded('typeRelation')) {
            $relation = $this->resource->getRelation('typeRelation');

            if ($relation instanceof DevotionalType) {
                return $relation->slug;
            }
        }

        // Fall back to the legacy string column kept during the deprecation
        // window (until MBA-032 drops it).
        $legacy = $this->resource->getAttributes()['type'] ?? null;

        return is_string($legacy) ? $legacy : null;
    }
}

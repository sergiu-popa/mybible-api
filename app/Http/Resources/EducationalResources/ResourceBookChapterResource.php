<?php

declare(strict_types=1);

namespace App\Http\Resources\EducationalResources;

use App\Domain\EducationalResources\Models\ResourceBookChapter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ResourceBookChapter
 */
class ResourceBookChapterResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'position' => $this->position,
            'title' => $this->title,
            'content' => $this->content,
            'audio_cdn_url' => $this->audio_cdn_url,
            'audio_embed' => $this->audio_embed,
            'duration_seconds' => $this->duration_seconds,
        ];
    }
}

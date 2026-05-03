<?php

declare(strict_types=1);

namespace App\Http\Resources\EducationalResources;

use App\Domain\EducationalResources\Models\ResourceBookChapter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ResourceBookChapter
 */
final class ResourceBookChapterListItemResource extends JsonResource
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
            'has_audio' => $this->hasAudio(),
            'duration_seconds' => $this->duration_seconds,
        ];
    }
}

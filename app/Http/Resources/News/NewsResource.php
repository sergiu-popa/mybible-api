<?php

declare(strict_types=1);

namespace App\Http\Resources\News;

use App\Domain\News\Models\News;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * @mixin News
 */
final class NewsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'summary' => $this->summary,
            'content' => $this->content,
            'published_at' => $this->published_at?->toIso8601String(),
            'image_url' => $this->resolveImageUrl(),
            'language' => $this->language,
        ];
    }

    private function resolveImageUrl(): ?string
    {
        if ($this->image_url === null || $this->image_url === '') {
            return null;
        }

        return Storage::disk('public')->url($this->image_url);
    }
}

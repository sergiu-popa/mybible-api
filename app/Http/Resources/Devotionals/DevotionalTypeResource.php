<?php

declare(strict_types=1);

namespace App\Http\Resources\Devotionals;

use App\Domain\Devotional\Models\DevotionalType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin DevotionalType
 */
final class DevotionalTypeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->title,
            'position' => $this->position,
            'language' => $this->language,
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Resources\Devotionals;

use App\Domain\Devotional\Models\Devotional;
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
            'type' => $this->type->value,
            'language' => $this->language,
            'title' => $this->title,
            'content' => $this->content,
            'passage' => $this->whenNotNull($this->passage),
            'author' => $this->whenNotNull($this->author),
        ];
    }
}

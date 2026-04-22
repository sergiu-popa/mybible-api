<?php

declare(strict_types=1);

namespace App\Http\Resources\Collections;

use App\Domain\Collections\Models\CollectionTopic;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CollectionTopic
 */
final class CollectionTopicResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'language' => $this->language,
            'reference_count' => (int) ($this->reference_count ?? 0),
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Resources\Collections;

use App\Domain\Collections\DataTransferObjects\ResolvedCollectionReference;
use App\Domain\Collections\Models\CollectionTopic;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CollectionTopic
 */
final class CollectionTopicDetailResource extends JsonResource
{
    /**
     * @param  array<int, ResolvedCollectionReference>  $resolvedReferences
     */
    public function __construct(
        CollectionTopic $topic,
        private readonly array $resolvedReferences,
    ) {
        parent::__construct($topic);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'image_url' => $this->image_cdn_url,
            'language' => $this->language,
            'collection_id' => $this->collection_id,
            'references' => ResolvedCollectionReferenceResource::collection($this->resolvedReferences),
        ];
    }
}

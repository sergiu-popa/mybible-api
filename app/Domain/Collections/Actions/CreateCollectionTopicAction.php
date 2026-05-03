<?php

declare(strict_types=1);

namespace App\Domain\Collections\Actions;

use App\Domain\Collections\DataTransferObjects\CreateCollectionTopicData;
use App\Domain\Collections\Models\CollectionTopic;

final class CreateCollectionTopicAction
{
    public function handle(CreateCollectionTopicData $data): CollectionTopic
    {
        return CollectionTopic::query()->create([
            'collection_id' => $data->collectionId,
            'language' => $data->language,
            'name' => $data->name,
            'description' => $data->description,
            'image_cdn_url' => $data->imageCdnUrl,
            'position' => $data->position,
        ]);
    }
}

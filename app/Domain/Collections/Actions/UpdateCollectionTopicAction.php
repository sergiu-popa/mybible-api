<?php

declare(strict_types=1);

namespace App\Domain\Collections\Actions;

use App\Domain\Collections\DataTransferObjects\UpdateCollectionTopicData;
use App\Domain\Collections\Models\CollectionTopic;

final class UpdateCollectionTopicAction
{
    public function handle(CollectionTopic $topic, UpdateCollectionTopicData $data): CollectionTopic
    {
        $attributes = [];
        if ($data->nameProvided && $data->name !== null) {
            $attributes['name'] = $data->name;
        }
        if ($data->descriptionProvided) {
            $attributes['description'] = $data->description;
        }
        if ($data->imageCdnUrlProvided) {
            $attributes['image_cdn_url'] = $data->imageCdnUrl;
        }
        if ($data->positionProvided && $data->position !== null) {
            $attributes['position'] = $data->position;
        }

        if ($attributes !== []) {
            $topic->update($attributes);
        }

        return $topic->fresh() ?? $topic;
    }
}

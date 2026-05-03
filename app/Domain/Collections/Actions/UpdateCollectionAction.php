<?php

declare(strict_types=1);

namespace App\Domain\Collections\Actions;

use App\Domain\Collections\DataTransferObjects\UpdateCollectionData;
use App\Domain\Collections\Models\Collection;

final class UpdateCollectionAction
{
    public function handle(Collection $collection, UpdateCollectionData $data): Collection
    {
        $attributes = [];
        if ($data->slugProvided && $data->slug !== null) {
            $attributes['slug'] = $data->slug;
        }
        if ($data->nameProvided && $data->name !== null) {
            $attributes['name'] = $data->name;
        }
        if ($data->languageProvided && $data->language !== null) {
            $attributes['language'] = $data->language;
        }
        if ($data->positionProvided && $data->position !== null) {
            $attributes['position'] = $data->position;
        }

        if ($attributes !== []) {
            $collection->update($attributes);
        }

        return $collection->fresh() ?? $collection;
    }
}

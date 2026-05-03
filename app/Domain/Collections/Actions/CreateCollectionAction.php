<?php

declare(strict_types=1);

namespace App\Domain\Collections\Actions;

use App\Domain\Collections\DataTransferObjects\CreateCollectionData;
use App\Domain\Collections\Models\Collection;
use App\Domain\Collections\Support\CollectionsCacheKeys;
use Illuminate\Support\Facades\Cache;

final class CreateCollectionAction
{
    public function handle(CreateCollectionData $data): Collection
    {
        $collection = Collection::query()->create([
            'slug' => $data->slug,
            'name' => $data->name,
            'language' => $data->language,
            'position' => $data->position,
        ]);

        Cache::tags(CollectionsCacheKeys::tagsForTopicsList())->flush();

        return $collection;
    }
}

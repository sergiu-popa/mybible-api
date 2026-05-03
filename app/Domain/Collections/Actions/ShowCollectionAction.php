<?php

declare(strict_types=1);

namespace App\Domain\Collections\Actions;

use App\Domain\Collections\Models\Collection;
use App\Domain\Collections\Support\CollectionsCacheKeys;
use App\Http\Resources\Collections\CollectionDetailResource;
use App\Support\Caching\CachedRead;

final class ShowCollectionAction
{
    public function __construct(private readonly CachedRead $cache) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(Collection $collection): array
    {
        return $this->cache->read(
            CollectionsCacheKeys::collection($collection->slug),
            CollectionsCacheKeys::tagsForTopicsList(),
            3600,
            static function () use ($collection): array {
                $collection->load(['topics' => fn ($query) => $query->orderBy('position')]);

                return CollectionDetailResource::make($collection)
                    ->response(request())
                    ->getData(true);
            },
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Collections\Actions;

use App\Domain\Collections\Models\Collection;
use App\Domain\Collections\Support\CollectionsCacheKeys;
use App\Domain\Shared\Enums\Language;
use App\Http\Resources\Collections\CollectionResource;
use App\Support\Caching\CachedRead;

final class ListCollectionsAction
{
    public function __construct(private readonly CachedRead $cache) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(Language $language, int $page, int $perPage): array
    {
        return $this->cache->read(
            CollectionsCacheKeys::collectionsList($language, $page, $perPage),
            CollectionsCacheKeys::tagsForTopicsList(),
            3600,
            static function () use ($language, $page, $perPage): array {
                $paginator = Collection::query()
                    ->forLanguage($language)
                    ->withTopicsCount()
                    ->ordered()
                    ->paginate($perPage, page: $page);

                return CollectionResource::collection($paginator)
                    ->response(request())
                    ->getData(true);
            },
        );
    }
}

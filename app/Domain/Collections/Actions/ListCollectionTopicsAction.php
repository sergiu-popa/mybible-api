<?php

declare(strict_types=1);

namespace App\Domain\Collections\Actions;

use App\Domain\Collections\Models\CollectionTopic;
use App\Domain\Collections\Support\CollectionsCacheKeys;
use App\Domain\Shared\Enums\Language;
use App\Http\Resources\Collections\CollectionTopicResource;
use App\Support\Caching\CachedRead;

final class ListCollectionTopicsAction
{
    public function __construct(private readonly CachedRead $cache) {}

    /**
     * @return array<string, mixed>
     */
    public function execute(Language $language, int $page, int $perPage): array
    {
        return $this->cache->read(
            CollectionsCacheKeys::topicsList($language, $page, $perPage),
            CollectionsCacheKeys::tagsForTopicsList(),
            3600,
            static function () use ($language, $page, $perPage): array {
                $paginator = CollectionTopic::query()
                    ->forLanguage($language)
                    ->withReferenceCount()
                    ->ordered()
                    ->paginate($perPage, page: $page);

                return CollectionTopicResource::collection($paginator)
                    ->response(request())
                    ->getData(true);
            },
        );
    }
}

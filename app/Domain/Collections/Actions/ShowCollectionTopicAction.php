<?php

declare(strict_types=1);

namespace App\Domain\Collections\Actions;

use App\Domain\Collections\Models\CollectionTopic;
use App\Domain\Collections\Support\CollectionsCacheKeys;
use App\Domain\Shared\Enums\Language;
use App\Http\Resources\Collections\CollectionTopicDetailResource;
use App\Support\Caching\CachedRead;

final class ShowCollectionTopicAction
{
    public function __construct(
        private readonly CachedRead $cache,
        private readonly ResolveCollectionReferencesAction $resolveReferences,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function execute(CollectionTopic $topic, Language $language): array
    {
        $resolveReferences = $this->resolveReferences;

        return $this->cache->read(
            CollectionsCacheKeys::topic($topic->id, $language),
            CollectionsCacheKeys::tagsForTopic($topic->id),
            3600,
            static function () use ($topic, $language, $resolveReferences): array {
                $topic->load('references');

                $resolved = $resolveReferences->handle($topic->references, $language);

                return (new CollectionTopicDetailResource($topic, $resolved))
                    ->response(request())
                    ->getData(true);
            },
        );
    }
}

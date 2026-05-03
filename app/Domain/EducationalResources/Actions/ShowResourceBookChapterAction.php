<?php

declare(strict_types=1);

namespace App\Domain\EducationalResources\Actions;

use App\Domain\EducationalResources\Models\ResourceBook;
use App\Domain\EducationalResources\Models\ResourceBookChapter;
use App\Domain\EducationalResources\Support\ResourceBooksCacheKeys;
use App\Http\Resources\EducationalResources\ResourceBookChapterResource;
use App\Support\Caching\CachedRead;

final class ShowResourceBookChapterAction
{
    public function __construct(private readonly CachedRead $cache) {}

    /**
     * @return array<string, mixed>
     */
    public function execute(ResourceBook $book, ResourceBookChapter $chapter): array
    {
        return $this->cache->read(
            ResourceBooksCacheKeys::chapter($book->slug, $chapter->id),
            ResourceBooksCacheKeys::tagsForBook($book->id),
            600,
            static function () use ($chapter): array {
                return ResourceBookChapterResource::make($chapter)
                    ->response(request())
                    ->getData(true);
            },
        );
    }
}

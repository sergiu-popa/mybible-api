<?php

declare(strict_types=1);

namespace App\Domain\EducationalResources\Actions;

use App\Domain\EducationalResources\Models\ResourceBook;
use App\Domain\EducationalResources\Support\ResourceBooksCacheKeys;
use App\Domain\Shared\Enums\Language;
use App\Http\Resources\EducationalResources\ResourceBookListResource;
use App\Support\Caching\CachedRead;

final class ListResourceBooksAction
{
    public function __construct(private readonly CachedRead $cache) {}

    /**
     * @return array<string, mixed>
     */
    public function execute(?Language $language, int $page, int $perPage): array
    {
        return $this->cache->read(
            ResourceBooksCacheKeys::list($language, $page, $perPage),
            ResourceBooksCacheKeys::tagsForList(),
            3600,
            static function () use ($language, $page, $perPage): array {
                $query = ResourceBook::query()
                    ->withCount('chapters')
                    ->published();

                if ($language !== null) {
                    $query->forLanguage($language);
                }

                $paginator = $query->orderedForList()->paginate($perPage, page: $page);

                return ResourceBookListResource::collection($paginator)
                    ->response(request())
                    ->getData(true);
            },
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\EducationalResources\Actions;

use App\Domain\EducationalResources\Enums\ResourceType;
use App\Domain\EducationalResources\Models\EducationalResource;
use App\Domain\EducationalResources\Support\EducationalResourcesCacheKeys;
use App\Http\Resources\EducationalResources\EducationalResourceListResource;
use App\Support\Caching\CachedRead;

final class ListResourcesByCategoryAction
{
    public function __construct(private readonly CachedRead $cache) {}

    /**
     * @return array<string, mixed>
     */
    public function execute(int $categoryId, int $page, int $perPage, ?ResourceType $type): array
    {
        return $this->cache->read(
            EducationalResourcesCacheKeys::resourcesByCategory($categoryId, $page, $perPage, $type),
            EducationalResourcesCacheKeys::tagsForCategory($categoryId),
            3600,
            static function () use ($categoryId, $page, $perPage, $type): array {
                $query = EducationalResource::query()
                    ->where('resource_category_id', $categoryId)
                    ->latestPublished();

                if ($type !== null) {
                    $query->ofType($type);
                }

                $paginator = $query->paginate($perPage, page: $page);

                return EducationalResourceListResource::collection($paginator)
                    ->response(request())
                    ->getData(true);
            },
        );
    }
}

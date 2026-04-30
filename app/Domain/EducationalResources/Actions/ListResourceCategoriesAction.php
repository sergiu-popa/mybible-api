<?php

declare(strict_types=1);

namespace App\Domain\EducationalResources\Actions;

use App\Domain\EducationalResources\Models\ResourceCategory;
use App\Domain\EducationalResources\Support\EducationalResourcesCacheKeys;
use App\Domain\Shared\Enums\Language;
use App\Http\Resources\EducationalResources\ResourceCategoryResource;
use App\Support\Caching\CachedRead;

final class ListResourceCategoriesAction
{
    public function __construct(private readonly CachedRead $cache) {}

    /**
     * @return array<string, mixed>
     */
    public function execute(?Language $language, int $page, int $perPage): array
    {
        return $this->cache->read(
            EducationalResourcesCacheKeys::categories($language, $page, $perPage),
            EducationalResourcesCacheKeys::tagsForCategoriesList(),
            3600,
            static function () use ($language, $page, $perPage): array {
                $query = ResourceCategory::query()->withResourceCount();

                if ($language !== null) {
                    $query->forLanguage($language);
                }

                $paginator = $query
                    ->orderBy('position')
                    ->orderBy('id')
                    ->paginate($perPage, page: $page);

                return ResourceCategoryResource::collection($paginator)
                    ->response(request())
                    ->getData(true);
            },
        );
    }
}

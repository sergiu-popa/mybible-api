<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\EducationalResources;

use App\Domain\EducationalResources\Models\EducationalResource;
use App\Domain\EducationalResources\Models\ResourceCategory;
use App\Http\Requests\EducationalResources\ListResourcesByCategoryRequest;
use App\Http\Resources\EducationalResources\EducationalResourceListResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * @tags Educational Resources
 */
final class ListResourcesByCategoryController
{
    /**
     * List resources in a category.
     *
     * Returns the paginated resources within the given category, ordered
     * newest-first by `published_at`. Optional `?type=` filter restricts to
     * a specific `ResourceType` case.
     */
    public function __invoke(
        ListResourcesByCategoryRequest $request,
        ResourceCategory $category,
    ): AnonymousResourceCollection {
        $query = EducationalResource::query()
            ->where('resource_category_id', $category->id)
            ->latestPublished();

        $type = $request->resourceType();

        if ($type !== null) {
            $query->ofType($type);
        }

        return EducationalResourceListResource::collection(
            $query->paginate($request->perPage()),
        );
    }
}

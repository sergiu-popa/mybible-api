<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\EducationalResources;

use App\Domain\EducationalResources\Actions\ListResourcesByCategoryAction;
use App\Domain\EducationalResources\Models\ResourceCategory;
use App\Http\Requests\EducationalResources\ListResourcesByCategoryRequest;
use Illuminate\Http\JsonResponse;

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
        ListResourcesByCategoryAction $action,
    ): JsonResponse {
        $page = max(1, (int) $request->query('page', '1'));

        $payload = $action->execute(
            $category->id,
            $page,
            $request->perPage(),
            $request->resourceType(),
        );

        return response()->json($payload);
    }
}

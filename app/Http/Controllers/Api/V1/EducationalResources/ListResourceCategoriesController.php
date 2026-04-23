<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\EducationalResources;

use App\Domain\EducationalResources\Models\ResourceCategory;
use App\Http\Requests\EducationalResources\ListResourceCategoriesRequest;
use App\Http\Resources\EducationalResources\ResourceCategoryResource;
use Symfony\Component\HttpFoundation\Response;

/**
 * @tags Educational Resources
 */
final class ListResourceCategoriesController
{
    private const CACHE_CONTROL = 'public, max-age=3600';

    /**
     * List resource categories.
     *
     * Returns a paginated list of categories with their resource counts,
     * optionally filtered by the requested language. The response is
     * cacheable for an hour by public caches.
     */
    public function __invoke(ListResourceCategoriesRequest $request): Response
    {
        $query = ResourceCategory::query()->withResourceCount();

        $language = $request->languageFilter();

        if ($language !== null) {
            $query->forLanguage($language);
        }

        $paginator = $query->orderBy('id')->paginate($request->perPage());

        return ResourceCategoryResource::collection($paginator)
            ->response($request)
            ->header('Cache-Control', self::CACHE_CONTROL);
    }
}

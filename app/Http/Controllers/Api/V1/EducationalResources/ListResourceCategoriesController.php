<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\EducationalResources;

use App\Domain\EducationalResources\Actions\ListResourceCategoriesAction;
use App\Http\Requests\EducationalResources\ListResourceCategoriesRequest;
use Illuminate\Http\JsonResponse;

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
    public function __invoke(
        ListResourceCategoriesRequest $request,
        ListResourceCategoriesAction $action,
    ): JsonResponse {
        $page = max(1, (int) $request->query('page', '1'));

        $payload = $action->execute(
            $request->languageFilter(),
            $page,
            $request->perPage(),
        );

        return response()->json($payload)
            ->header('Cache-Control', self::CACHE_CONTROL);
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Favorites;

use App\Domain\Favorites\Actions\CreateFavoriteCategoryAction;
use App\Http\Requests\Favorites\CreateFavoriteCategoryRequest;
use App\Http\Resources\Favorites\FavoriteCategoryResource;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * @tags Favorites
 */
final class CreateFavoriteCategoryController
{
    /**
     * Create a new favorite category for the authenticated user.
     */
    public function __invoke(
        CreateFavoriteCategoryRequest $request,
        CreateFavoriteCategoryAction $action,
    ): JsonResponse {
        $category = $action->execute($request->toData());

        return FavoriteCategoryResource::make($category->loadCount('favorites'))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}

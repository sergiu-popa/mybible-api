<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Favorites;

use App\Domain\Favorites\Actions\UpdateFavoriteCategoryAction;
use App\Domain\Favorites\Models\FavoriteCategory;
use App\Http\Requests\Favorites\UpdateFavoriteCategoryRequest;
use App\Http\Resources\Favorites\FavoriteCategoryResource;

/**
 * @tags Favorites
 */
final class UpdateFavoriteCategoryController
{
    /**
     * Rename or recolor a favorite category. Owner only.
     */
    public function __invoke(
        UpdateFavoriteCategoryRequest $request,
        FavoriteCategory $category,
        UpdateFavoriteCategoryAction $action,
    ): FavoriteCategoryResource {
        $updated = $action->execute($request->toData());

        return FavoriteCategoryResource::make($updated->loadCount('favorites'));
    }
}

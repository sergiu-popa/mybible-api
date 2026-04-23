<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Favorites;

use App\Domain\Favorites\Actions\DeleteFavoriteCategoryAction;
use App\Domain\Favorites\Models\FavoriteCategory;
use App\Http\Requests\Favorites\DeleteFavoriteCategoryRequest;
use Illuminate\Http\Response;

/**
 * @tags Favorites
 */
final class DeleteFavoriteCategoryController
{
    /**
     * Delete a favorite category. Owner only. Any favorites assigned to
     * this category are reparented to the virtual "Uncategorized" bucket.
     */
    public function __invoke(
        DeleteFavoriteCategoryRequest $request,
        FavoriteCategory $category,
        DeleteFavoriteCategoryAction $action,
    ): Response {
        $action->execute($category);

        return response()->noContent();
    }
}

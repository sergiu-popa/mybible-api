<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Favorites;

use App\Domain\Favorites\Models\Favorite;
use App\Domain\Favorites\Models\FavoriteCategory;
use App\Http\Requests\Favorites\ListFavoriteCategoriesRequest;
use App\Http\Resources\Favorites\FavoriteCategoryResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;

/**
 * @tags Favorites
 */
final class ListFavoriteCategoriesController
{
    /**
     * List the authenticated user's favorite categories.
     *
     * When the caller has at least one favorite with no category
     * assigned, a synthetic "Uncategorized" entry with `id: null` is
     * prepended to the first page's data array. This is a virtual
     * category — no database row exists for it.
     */
    public function __invoke(ListFavoriteCategoriesRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $perPage = (int) ($request->validated('per_page') ?? 15);

        $paginator = FavoriteCategory::query()
            ->forUser($user)
            ->withCount('favorites')
            ->orderedByName()
            ->paginate($perPage);

        $payload = FavoriteCategoryResource::collection($paginator)
            ->response()
            ->getData(true);

        $isFirstPage = ($paginator->currentPage() === 1);

        if ($isFirstPage && $this->hasUncategorizedFavorites($user)) {
            $uncategorized = [
                'id' => null,
                'name' => 'Uncategorized',
                'color' => null,
                'favorites_count' => Favorite::query()
                    ->forUser($user)
                    ->forCategory(null)
                    ->count(),
            ];

            array_unshift($payload['data'], $uncategorized);
        }

        return new JsonResponse($payload);
    }

    private function hasUncategorizedFavorites(User $user): bool
    {
        return Favorite::query()
            ->forUser($user)
            ->forCategory(null)
            ->exists();
    }
}

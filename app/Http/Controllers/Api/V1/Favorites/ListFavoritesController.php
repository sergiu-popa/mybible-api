<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Favorites;

use App\Domain\Favorites\Models\Favorite;
use App\Http\Requests\Favorites\ListFavoritesRequest;
use App\Http\Resources\Favorites\FavoriteResource;
use App\Models\User;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * @tags Favorites
 */
final class ListFavoritesController
{
    /**
     * List the authenticated user's favorites.
     *
     * Supports optional filters:
     *   - `category=<id>` — match a specific category
     *   - `category=uncategorized` (or empty string, or `null`) — match the
     *     virtual Uncategorized bucket
     *   - `book=<ABBR>` — match favorites in the given Bible book
     */
    public function __invoke(ListFavoritesRequest $request): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();

        $query = Favorite::query()
            ->forUser($user)
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        $categoryFilter = $request->categoryFilter();
        if ($categoryFilter !== ListFavoritesRequest::NO_CATEGORY_FILTER) {
            $query->forCategory($categoryFilter);
        }

        $book = $request->bookFilter();
        if ($book !== null) {
            $query->forBook($book);
        }

        $perPage = (int) ($request->validated('per_page') ?? 15);

        return FavoriteResource::collection($query->paginate($perPage));
    }
}

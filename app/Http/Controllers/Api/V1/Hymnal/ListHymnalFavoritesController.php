<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Hymnal;

use App\Domain\Hymnal\Models\HymnalFavorite;
use App\Http\Requests\Hymnal\ListHymnalFavoritesRequest;
use App\Http\Resources\Hymnal\HymnalFavoriteResource;
use App\Models\User;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * @tags Hymnal
 */
final class ListHymnalFavoritesController
{
    /**
     * List the caller's hymnal favorites.
     *
     * Returns a paginated list of the authenticated user's favorite hymnal
     * songs, with the full song payload embedded in each row.
     */
    public function __invoke(ListHymnalFavoritesRequest $request): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();

        $favorites = HymnalFavorite::query()
            ->forUser($user)
            ->withSong()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($request->perPage());

        return HymnalFavoriteResource::collection($favorites);
    }
}

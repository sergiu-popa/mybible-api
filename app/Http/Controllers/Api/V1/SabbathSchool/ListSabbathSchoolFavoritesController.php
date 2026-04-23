<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\SabbathSchool;

use App\Domain\SabbathSchool\Models\SabbathSchoolFavorite;
use App\Http\Requests\SabbathSchool\ListSabbathSchoolFavoritesRequest;
use App\Http\Resources\SabbathSchool\SabbathSchoolFavoriteResource;
use App\Models\User;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * @tags Sabbath School
 */
final class ListSabbathSchoolFavoritesController
{
    /**
     * List the caller's Sabbath School favorites.
     *
     * Returns a paginated list of the authenticated user's favorites
     * (whole-lesson and segment-scoped), newest first.
     */
    public function __invoke(ListSabbathSchoolFavoritesRequest $request): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();

        $paginator = SabbathSchoolFavorite::query()
            ->forUser($user)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($request->perPage());

        return SabbathSchoolFavoriteResource::collection($paginator);
    }
}

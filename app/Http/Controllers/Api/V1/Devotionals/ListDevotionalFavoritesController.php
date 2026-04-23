<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Devotionals;

use App\Domain\Devotional\Models\DevotionalFavorite;
use App\Http\Requests\Devotionals\ListDevotionalFavoritesRequest;
use App\Http\Resources\Devotionals\DevotionalFavoriteResource;
use App\Models\User;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * @tags Devotionals
 */
final class ListDevotionalFavoritesController
{
    /**
     * List the authenticated user's devotional favorites, newest first,
     * with the embedded devotional resource.
     */
    public function __invoke(ListDevotionalFavoritesRequest $request): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();

        $paginator = DevotionalFavorite::query()
            ->forUser($user)
            ->withDevotional()
            ->newestFirst()
            ->paginate($request->perPage());

        return DevotionalFavoriteResource::collection($paginator);
    }
}

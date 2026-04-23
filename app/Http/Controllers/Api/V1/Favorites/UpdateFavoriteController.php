<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Favorites;

use App\Domain\Favorites\Actions\UpdateFavoriteAction;
use App\Domain\Favorites\Models\Favorite;
use App\Http\Requests\Favorites\UpdateFavoriteRequest;
use App\Http\Resources\Favorites\FavoriteResource;

/**
 * @tags Favorites
 */
final class UpdateFavoriteController
{
    /**
     * Update a favorite's category or note. Owner only. The `reference`
     * field is immutable after creation — attempting to change it yields
     * a 422 validation error.
     */
    public function __invoke(
        UpdateFavoriteRequest $request,
        Favorite $favorite,
        UpdateFavoriteAction $action,
    ): FavoriteResource {
        return FavoriteResource::make($action->execute($request->toData()));
    }
}

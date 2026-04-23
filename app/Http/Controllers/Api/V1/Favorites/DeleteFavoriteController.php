<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Favorites;

use App\Domain\Favorites\Actions\DeleteFavoriteAction;
use App\Domain\Favorites\Models\Favorite;
use App\Http\Requests\Favorites\DeleteFavoriteRequest;
use Illuminate\Http\Response;

/**
 * @tags Favorites
 */
final class DeleteFavoriteController
{
    /**
     * Delete a favorite. Owner only.
     */
    public function __invoke(
        DeleteFavoriteRequest $request,
        Favorite $favorite,
        DeleteFavoriteAction $action,
    ): Response {
        $action->execute($favorite);

        return response()->noContent();
    }
}

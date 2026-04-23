<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Favorites;

use App\Domain\Favorites\Actions\CreateFavoriteAction;
use App\Http\Requests\Favorites\CreateFavoriteRequest;
use App\Http\Resources\Favorites\FavoriteResource;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * @tags Favorites
 */
final class CreateFavoriteController
{
    /**
     * Create a favorite for the authenticated user.
     *
     * The `reference` field is validated against the MBA-006 parser and
     * stored in canonical form. It is immutable after creation.
     */
    public function __invoke(
        CreateFavoriteRequest $request,
        CreateFavoriteAction $action,
    ): JsonResponse {
        $favorite = $action->execute($request->toData());

        return FavoriteResource::make($favorite)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}

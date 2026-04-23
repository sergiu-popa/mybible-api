<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Hymnal;

use App\Domain\Hymnal\Actions\ToggleHymnalFavoriteAction;
use App\Http\Requests\Hymnal\ToggleHymnalFavoriteRequest;
use App\Http\Resources\Hymnal\HymnalFavoriteResource;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * @tags Hymnal
 */
final class ToggleHymnalFavoriteController
{
    /**
     * Toggle a hymnal favorite.
     *
     * Flips the favorite state for the given song. Returns 201 with the new
     * favorite on insert, or 200 with `{ deleted: true }` when removing.
     */
    public function __invoke(
        ToggleHymnalFavoriteRequest $request,
        ToggleHymnalFavoriteAction $action,
    ): JsonResponse {
        $result = $action->execute($request->toData());

        if ($result->created) {
            $result->favorite->load('song.book');

            return HymnalFavoriteResource::make($result->favorite)
                ->response()
                ->setStatusCode(Response::HTTP_CREATED);
        }

        return response()->json(['deleted' => true], Response::HTTP_OK);
    }
}

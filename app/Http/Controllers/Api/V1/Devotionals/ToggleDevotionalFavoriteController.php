<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Devotionals;

use App\Domain\Devotional\Actions\ToggleDevotionalFavoriteAction;
use App\Http\Requests\Devotionals\ToggleDevotionalFavoriteRequest;
use App\Http\Resources\Devotionals\DevotionalFavoriteResource;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * @tags Devotionals
 */
final class ToggleDevotionalFavoriteController
{
    /**
     * Flip the caller's favorite state for a devotional.
     *
     * Returns `201 Created` with the new favorite (including the embedded
     * devotional) on insert, or `200 OK` with `{ deleted: true }` on removal.
     */
    public function __invoke(
        ToggleDevotionalFavoriteRequest $request,
        ToggleDevotionalFavoriteAction $action,
    ): JsonResponse {
        $result = $action->execute($request->toData());

        if ($result->created && $result->favorite !== null) {
            return DevotionalFavoriteResource::make($result->favorite)
                ->response()
                ->setStatusCode(Response::HTTP_CREATED);
        }

        return response()->json(['deleted' => true]);
    }
}

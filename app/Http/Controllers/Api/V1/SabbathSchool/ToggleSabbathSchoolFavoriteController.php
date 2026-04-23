<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\SabbathSchool;

use App\Domain\SabbathSchool\Actions\ToggleSabbathSchoolFavoriteAction;
use App\Http\Requests\SabbathSchool\ToggleSabbathSchoolFavoriteRequest;
use App\Http\Resources\SabbathSchool\SabbathSchoolFavoriteResource;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * @tags Sabbath School
 */
final class ToggleSabbathSchoolFavoriteController
{
    /**
     * Toggle a Sabbath School favorite.
     *
     * Returns `201 Created` with the new favorite on insert, or `200 OK`
     * with `{ deleted: true }` when the row is removed. Lesson-level and
     * segment-level favorites coexist on the unique index and are toggled
     * independently.
     */
    public function __invoke(
        ToggleSabbathSchoolFavoriteRequest $request,
        ToggleSabbathSchoolFavoriteAction $action,
    ): JsonResponse {
        $result = $action->execute($request->toData());

        if ($result->created && $result->favorite !== null) {
            return SabbathSchoolFavoriteResource::make($result->favorite)
                ->response()
                ->setStatusCode(Response::HTTP_CREATED);
        }

        return response()->json(['deleted' => true]);
    }
}

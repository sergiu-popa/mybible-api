<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\SabbathSchool;

use App\Domain\SabbathSchool\Actions\ToggleSabbathSchoolHighlightAction;
use App\Http\Requests\SabbathSchool\ToggleSabbathSchoolHighlightRequest;
use App\Http\Resources\SabbathSchool\SabbathSchoolHighlightResource;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * @tags Sabbath School
 */
final class ToggleSabbathSchoolHighlightController
{
    /**
     * Toggle a Sabbath School highlight.
     *
     * Returns `201 Created` with the new highlight on insert, or `200 OK`
     * with `{ deleted: true }` when the existing (segment, passage) pair is
     * removed.
     */
    public function __invoke(
        ToggleSabbathSchoolHighlightRequest $request,
        ToggleSabbathSchoolHighlightAction $action,
    ): JsonResponse {
        $result = $action->execute($request->toData());

        if ($result->created && $result->highlight !== null) {
            return SabbathSchoolHighlightResource::make($result->highlight)
                ->response()
                ->setStatusCode(Response::HTTP_CREATED);
        }

        return response()->json(['deleted' => true]);
    }
}

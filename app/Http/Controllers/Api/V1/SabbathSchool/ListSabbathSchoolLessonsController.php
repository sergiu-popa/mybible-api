<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\SabbathSchool;

use App\Domain\SabbathSchool\Actions\ListSabbathSchoolLessonsAction;
use App\Http\Requests\SabbathSchool\ListSabbathSchoolLessonsRequest;
use Illuminate\Http\JsonResponse;

/**
 * @tags Sabbath School
 */
final class ListSabbathSchoolLessonsController
{
    private const CACHE_MAX_AGE = 3600;

    /**
     * List Sabbath School lessons.
     *
     * Returns a paginated list of published lessons in the resolved language,
     * newest first. Supports `?trimester=` and `?age_group=` filters.
     */
    public function __invoke(
        ListSabbathSchoolLessonsRequest $request,
        ListSabbathSchoolLessonsAction $action,
    ): JsonResponse {
        $page = max(1, (int) $request->query('page', '1'));

        $payload = $action->execute(
            $request->resolvedLanguage(),
            $page,
            $request->perPage(),
            $request->trimesterId(),
            $request->ageGroup(),
        );

        return response()->json($payload)
            ->header('Cache-Control', 'public, max-age=' . self::CACHE_MAX_AGE);
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\SabbathSchool;

use App\Domain\SabbathSchool\Models\SabbathSchoolLesson;
use App\Http\Requests\SabbathSchool\ListSabbathSchoolLessonsRequest;
use App\Http\Resources\SabbathSchool\SabbathSchoolLessonSummaryResource;
use Illuminate\Http\JsonResponse;

/**
 * @tags Sabbath School
 */
final class ListSabbathSchoolLessonsController
{
    /**
     * List Sabbath School lessons.
     *
     * Returns a paginated list of published lessons in the resolved language,
     * newest first. Intermediate caches MAY keep the response for 1 hour; the
     * payload never embeds per-user state.
     */
    public function __invoke(ListSabbathSchoolLessonsRequest $request): JsonResponse
    {
        $paginator = SabbathSchoolLesson::query()
            ->published()
            ->forLanguage($request->resolvedLanguage())
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate($request->perPage());

        return SabbathSchoolLessonSummaryResource::collection($paginator)
            ->response()
            ->header('Cache-Control', 'public, max-age=3600');
    }
}

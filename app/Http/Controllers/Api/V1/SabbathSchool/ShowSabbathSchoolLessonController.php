<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\SabbathSchool;

use App\Domain\SabbathSchool\Models\SabbathSchoolLesson;
use App\Domain\SabbathSchool\QueryBuilders\SabbathSchoolLessonQueryBuilder;
use App\Http\Requests\SabbathSchool\ShowSabbathSchoolLessonRequest;
use App\Http\Resources\SabbathSchool\SabbathSchoolLessonResource;
use Illuminate\Http\JsonResponse;

/**
 * @tags Sabbath School
 */
final class ShowSabbathSchoolLessonController
{
    /**
     * Show a Sabbath School lesson.
     *
     * Returns the lesson detail including all segments and their questions.
     * The eager-load is applied during route-model binding via
     * {@see SabbathSchoolLesson::resolveRouteBinding()} +
     * {@see SabbathSchoolLessonQueryBuilder::withLessonDetail()}
     * so the controller sees a fully-hydrated graph and N+1 is guarded by the
     * builder rather than duplicated here.
     */
    public function __invoke(
        ShowSabbathSchoolLessonRequest $request,
        SabbathSchoolLesson $lesson,
    ): JsonResponse {
        return SabbathSchoolLessonResource::make($lesson)
            ->response()
            ->header('Cache-Control', 'public, max-age=3600');
    }
}

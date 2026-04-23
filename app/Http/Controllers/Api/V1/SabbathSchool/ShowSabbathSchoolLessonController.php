<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\SabbathSchool;

use App\Domain\SabbathSchool\Models\SabbathSchoolLesson;
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
     * The eager-load is pre-declared on the query builder to avoid N+1 on
     * large fixtures (see SabbathSchoolLessonQueryBuilder::withLessonDetail).
     */
    public function __invoke(
        ShowSabbathSchoolLessonRequest $request,
        SabbathSchoolLesson $lesson,
    ): JsonResponse {
        $lesson->load(['segments.questions']);

        return SabbathSchoolLessonResource::make($lesson)
            ->response()
            ->header('Cache-Control', 'public, max-age=3600');
    }
}

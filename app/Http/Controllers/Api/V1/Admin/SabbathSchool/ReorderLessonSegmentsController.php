<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\SabbathSchool;

use App\Domain\SabbathSchool\Actions\ReorderLessonSegmentsAction;
use App\Domain\SabbathSchool\Models\SabbathSchoolLesson;
use App\Http\Requests\Admin\ReorderRequest;
use Illuminate\Http\JsonResponse;

final class ReorderLessonSegmentsController
{
    public function __invoke(
        ReorderRequest $request,
        SabbathSchoolLesson $lesson,
        ReorderLessonSegmentsAction $action,
    ): JsonResponse {
        $action->execute($lesson, $request->ids());

        return response()->json(['message' => 'Reordered.']);
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\SabbathSchool;

use App\Domain\SabbathSchool\Actions\DeleteLessonAction;
use App\Domain\SabbathSchool\Models\SabbathSchoolLesson;
use App\Http\Requests\Admin\SabbathSchool\DeleteLessonRequest;
use Illuminate\Http\Response;

final class DeleteLessonController
{
    public function __invoke(
        DeleteLessonRequest $request,
        SabbathSchoolLesson $lesson,
        DeleteLessonAction $action,
    ): Response {
        $action->execute($lesson);

        return response()->noContent();
    }
}

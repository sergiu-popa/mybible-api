<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\SabbathSchool;

use App\Domain\SabbathSchool\Actions\UpdateLessonAction;
use App\Domain\SabbathSchool\Models\SabbathSchoolLesson;
use App\Http\Requests\Admin\SabbathSchool\UpdateLessonRequest;
use App\Http\Resources\SabbathSchool\SabbathSchoolLessonResource;

final class UpdateLessonController
{
    public function __invoke(
        UpdateLessonRequest $request,
        SabbathSchoolLesson $lesson,
        UpdateLessonAction $action,
    ): SabbathSchoolLessonResource {
        return SabbathSchoolLessonResource::make(
            $action->execute($lesson, $request->toData()),
        );
    }
}

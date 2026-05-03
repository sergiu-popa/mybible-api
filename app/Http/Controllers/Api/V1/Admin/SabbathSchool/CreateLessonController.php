<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\SabbathSchool;

use App\Domain\SabbathSchool\Actions\CreateLessonAction;
use App\Http\Requests\Admin\SabbathSchool\CreateLessonRequest;
use App\Http\Resources\SabbathSchool\SabbathSchoolLessonResource;
use Illuminate\Http\JsonResponse;

final class CreateLessonController
{
    public function __invoke(
        CreateLessonRequest $request,
        CreateLessonAction $action,
    ): JsonResponse {
        $lesson = $action->execute($request->toData());

        return SabbathSchoolLessonResource::make($lesson)
            ->response()
            ->setStatusCode(201);
    }
}

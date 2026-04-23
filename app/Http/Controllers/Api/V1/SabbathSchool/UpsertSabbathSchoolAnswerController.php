<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\SabbathSchool;

use App\Domain\SabbathSchool\Actions\UpsertSabbathSchoolAnswerAction;
use App\Domain\SabbathSchool\Models\SabbathSchoolQuestion;
use App\Http\Requests\SabbathSchool\UpsertSabbathSchoolAnswerRequest;
use App\Http\Resources\SabbathSchool\SabbathSchoolAnswerResource;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * @tags Sabbath School
 */
final class UpsertSabbathSchoolAnswerController
{
    /**
     * Create or overwrite the caller's answer for the given question.
     *
     * Returns `201 Created` on first save, `200 OK` on subsequent overwrites.
     * Only one answer per `(user, question)` is ever stored.
     */
    public function __invoke(
        UpsertSabbathSchoolAnswerRequest $request,
        SabbathSchoolQuestion $question,
        UpsertSabbathSchoolAnswerAction $action,
    ): JsonResponse {
        $result = $action->execute($request->toData());

        $status = $result->created ? Response::HTTP_CREATED : Response::HTTP_OK;

        return SabbathSchoolAnswerResource::make($result->answer)
            ->response()
            ->setStatusCode($status);
    }
}

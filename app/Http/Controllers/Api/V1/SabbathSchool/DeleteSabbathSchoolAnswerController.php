<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\SabbathSchool;

use App\Domain\SabbathSchool\Actions\DeleteSabbathSchoolAnswerAction;
use App\Domain\SabbathSchool\Models\SabbathSchoolQuestion;
use App\Http\Requests\SabbathSchool\DeleteSabbathSchoolAnswerRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Response;

/**
 * @tags Sabbath School
 */
final class DeleteSabbathSchoolAnswerController
{
    /**
     * Delete the caller's answer for the given question.
     *
     * Returns 204 on success; 404 when no answer exists for the caller (so
     * deletion acts as an existence check, matching the GET endpoint).
     */
    public function __invoke(
        DeleteSabbathSchoolAnswerRequest $request,
        SabbathSchoolQuestion $question,
        DeleteSabbathSchoolAnswerAction $action,
    ): Response {
        /** @var User $user */
        $user = $request->user();

        $deleted = $action->execute($user, $question);

        if (! $deleted) {
            throw new ModelNotFoundException;
        }

        return response()->noContent();
    }
}

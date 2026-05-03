<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\SabbathSchool;

use App\Domain\SabbathSchool\Actions\DeleteSabbathSchoolAnswerAction;
use App\Domain\SabbathSchool\Models\SabbathSchoolSegmentContent;
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
     * Delete the caller's answer for the given segment content.
     *
     * Returns 204 on success; 404 when no answer exists for the caller (so
     * deletion acts as an existence check, matching the GET endpoint).
     */
    public function __invoke(
        DeleteSabbathSchoolAnswerRequest $request,
        SabbathSchoolSegmentContent $content,
        DeleteSabbathSchoolAnswerAction $action,
    ): Response {
        /** @var User $user */
        $user = $request->user();

        $deleted = $action->execute($user, $content);

        if (! $deleted) {
            throw new ModelNotFoundException;
        }

        return response()->noContent();
    }
}

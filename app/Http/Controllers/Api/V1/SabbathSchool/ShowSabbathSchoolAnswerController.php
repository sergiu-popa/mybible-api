<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\SabbathSchool;

use App\Domain\SabbathSchool\Models\SabbathSchoolAnswer;
use App\Domain\SabbathSchool\Models\SabbathSchoolSegmentContent;
use App\Http\Requests\SabbathSchool\ShowSabbathSchoolAnswerRequest;
use App\Http\Resources\SabbathSchool\SabbathSchoolAnswerResource;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * @tags Sabbath School
 */
final class ShowSabbathSchoolAnswerController
{
    /**
     * Return the caller's answer for the given segment content.
     *
     * Hides existence from other users — when no answer row exists for the
     * authenticated caller, the response is a 404, not a 403.
     */
    public function __invoke(
        ShowSabbathSchoolAnswerRequest $request,
        SabbathSchoolSegmentContent $content,
    ): SabbathSchoolAnswerResource {
        /** @var User $user */
        $user = $request->user();

        $answer = SabbathSchoolAnswer::query()
            ->forUser($user)
            ->forSegmentContent($content->id)
            ->first();

        if ($answer === null) {
            throw new ModelNotFoundException;
        }

        return SabbathSchoolAnswerResource::make($answer);
    }
}

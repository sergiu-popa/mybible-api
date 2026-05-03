<?php

declare(strict_types=1);

namespace App\Domain\SabbathSchool\Actions;

use App\Domain\SabbathSchool\Models\SabbathSchoolAnswer;
use App\Domain\SabbathSchool\Models\SabbathSchoolSegmentContent;
use App\Models\User;

final class DeleteSabbathSchoolAnswerAction
{
    /**
     * Delete the caller's answer for the given segment content.
     *
     * Returns `true` when a row was deleted, `false` when none existed.
     * The controller decides whether to surface a 404 for the missing
     * row case (it does — see DeleteSabbathSchoolAnswerController).
     */
    public function execute(User $user, SabbathSchoolSegmentContent $segmentContent): bool
    {
        $answer = SabbathSchoolAnswer::query()
            ->forUser($user)
            ->forSegmentContent($segmentContent->id)
            ->first();

        if ($answer === null) {
            return false;
        }

        $answer->delete();

        return true;
    }
}

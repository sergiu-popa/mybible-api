<?php

declare(strict_types=1);

namespace App\Domain\SabbathSchool\Actions;

use App\Domain\SabbathSchool\DataTransferObjects\UpsertSabbathSchoolAnswerData;
use App\Domain\SabbathSchool\DataTransferObjects\UpsertSabbathSchoolAnswerResult;
use App\Domain\SabbathSchool\Models\SabbathSchoolAnswer;
use Illuminate\Support\Facades\DB;

final class UpsertSabbathSchoolAnswerAction
{
    /**
     * Insert or overwrite the caller's single answer for a question.
     *
     * Matches the Symfony overwrite semantic: there is at most one row per
     * `(user_id, sabbath_school_question_id)` and a subsequent save wins.
     * The result flag `created` distinguishes 201 (insert) from 200 (update)
     * at the controller layer.
     */
    public function execute(UpsertSabbathSchoolAnswerData $data): UpsertSabbathSchoolAnswerResult
    {
        return DB::transaction(function () use ($data): UpsertSabbathSchoolAnswerResult {
            $existing = SabbathSchoolAnswer::query()
                ->forUser($data->user)
                ->forQuestion($data->question->id)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                $existing->content = $data->content;
                $existing->save();

                return new UpsertSabbathSchoolAnswerResult($existing, false);
            }

            $answer = SabbathSchoolAnswer::query()->create([
                'user_id' => $data->user->id,
                'sabbath_school_question_id' => $data->question->id,
                'content' => $data->content,
            ]);

            return new UpsertSabbathSchoolAnswerResult($answer, true);
        });
    }
}

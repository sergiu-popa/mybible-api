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
     * Insert or overwrite the caller's single answer for a question
     * (now keyed by `segment_content_id`).
     */
    public function execute(UpsertSabbathSchoolAnswerData $data): UpsertSabbathSchoolAnswerResult
    {
        return DB::transaction(function () use ($data): UpsertSabbathSchoolAnswerResult {
            $existing = SabbathSchoolAnswer::query()
                ->forUser($data->user)
                ->forSegmentContent($data->segmentContent->id)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                $existing->content = $data->content;
                $existing->save();

                return new UpsertSabbathSchoolAnswerResult($existing, false);
            }

            $answer = SabbathSchoolAnswer::query()->create([
                'user_id' => $data->user->id,
                'segment_content_id' => $data->segmentContent->id,
                'content' => $data->content,
            ]);

            return new UpsertSabbathSchoolAnswerResult($answer, true);
        });
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Olympiad\Actions;

use App\Domain\Olympiad\DataTransferObjects\SubmitOlympiadAnswersData;
use App\Domain\Olympiad\Exceptions\OlympiadAnswerNotInQuestionException;
use App\Domain\Olympiad\Exceptions\OlympiadAttemptAlreadyFinishedException;
use App\Domain\Olympiad\Exceptions\OlympiadAttemptThemeMismatchException;
use App\Domain\Olympiad\Models\OlympiadAnswer;
use App\Domain\Olympiad\Models\OlympiadAttemptAnswer;
use App\Domain\Olympiad\Models\OlympiadQuestion;
use App\Domain\Reference\ChapterRange;
use Illuminate\Support\Facades\DB;

final class SubmitOlympiadAttemptAnswersAction
{
    public function handle(SubmitOlympiadAnswersData $data): void
    {
        $attempt = $data->attempt;

        if ($attempt->completed_at !== null) {
            throw new OlympiadAttemptAlreadyFinishedException;
        }

        $range = ChapterRange::fromSegment($attempt->chapters_label);
        $themeQuestionUuids = OlympiadQuestion::query()
            ->matchingTheme($attempt->book, $range, $attempt->language)
            ->pluck('uuid', 'id');
        $themeQuestionIdByUuid = $themeQuestionUuids->flip();

        DB::transaction(function () use ($data, $attempt, $themeQuestionIdByUuid): void {
            foreach ($data->lines as $line) {
                $questionId = $themeQuestionIdByUuid->get($line->questionUuid);

                if ($questionId === null) {
                    throw new OlympiadAttemptThemeMismatchException;
                }

                $selectedAnswerId = null;
                $isCorrect = false;

                if ($line->selectedAnswerUuid !== null) {
                    $answer = OlympiadAnswer::query()
                        ->where('uuid', $line->selectedAnswerUuid)
                        ->first();

                    if ($answer === null || $answer->olympiad_question_id !== $questionId) {
                        throw new OlympiadAnswerNotInQuestionException;
                    }

                    $selectedAnswerId = $answer->id;
                    $isCorrect = $answer->is_correct;
                }

                $now = now();
                $existing = OlympiadAttemptAnswer::query()
                    ->where('attempt_id', $attempt->id)
                    ->where('olympiad_question_id', $questionId)
                    ->exists();

                if ($existing) {
                    OlympiadAttemptAnswer::query()
                        ->where('attempt_id', $attempt->id)
                        ->where('olympiad_question_id', $questionId)
                        ->update([
                            'selected_answer_id' => $selectedAnswerId,
                            'is_correct' => $isCorrect,
                            'updated_at' => $now,
                        ]);
                } else {
                    OlympiadAttemptAnswer::query()->insert([
                        'attempt_id' => $attempt->id,
                        'olympiad_question_id' => $questionId,
                        'selected_answer_id' => $selectedAnswerId,
                        'is_correct' => $isCorrect,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        });
    }
}

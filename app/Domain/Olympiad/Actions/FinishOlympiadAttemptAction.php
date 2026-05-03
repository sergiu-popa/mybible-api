<?php

declare(strict_types=1);

namespace App\Domain\Olympiad\Actions;

use App\Domain\Olympiad\Exceptions\OlympiadAttemptAlreadyFinishedException;
use App\Domain\Olympiad\Models\OlympiadAttempt;
use App\Domain\Olympiad\Models\OlympiadAttemptAnswer;
use Carbon\CarbonImmutable;

final class FinishOlympiadAttemptAction
{
    public function handle(OlympiadAttempt $attempt): OlympiadAttempt
    {
        if ($attempt->completed_at !== null) {
            throw new OlympiadAttemptAlreadyFinishedException;
        }

        $score = OlympiadAttemptAnswer::query()
            ->where('attempt_id', $attempt->id)
            ->where('is_correct', true)
            ->count();

        $attempt->update([
            'score' => $score,
            'completed_at' => CarbonImmutable::now(),
        ]);

        return $attempt->fresh() ?? $attempt;
    }
}

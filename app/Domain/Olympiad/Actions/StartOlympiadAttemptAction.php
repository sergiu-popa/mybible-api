<?php

declare(strict_types=1);

namespace App\Domain\Olympiad\Actions;

use App\Domain\Olympiad\DataTransferObjects\StartOlympiadAttemptData;
use App\Domain\Olympiad\Models\OlympiadAttempt;
use App\Domain\Olympiad\Models\OlympiadQuestion;
use Carbon\CarbonImmutable;

final class StartOlympiadAttemptAction
{
    /**
     * @return array{0: OlympiadAttempt, 1: list<string>}
     */
    public function handle(StartOlympiadAttemptData $data): array
    {
        $questions = OlympiadQuestion::query()
            ->matchingTheme($data->book, $data->range, $data->language)
            ->orderBy('id')
            ->get();

        $attempt = OlympiadAttempt::query()->create([
            'user_id' => $data->user->id,
            'book' => $data->book,
            'chapters_label' => $data->range->toCanonicalSegment(),
            'language' => $data->language->value,
            'score' => 0,
            'total' => $questions->count(),
            'started_at' => CarbonImmutable::now(),
            'completed_at' => null,
        ]);

        /** @var list<string> $uuids */
        $uuids = $questions->pluck('uuid')->all();

        return [$attempt, $uuids];
    }
}

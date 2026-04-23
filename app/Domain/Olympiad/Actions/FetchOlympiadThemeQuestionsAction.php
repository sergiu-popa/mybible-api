<?php

declare(strict_types=1);

namespace App\Domain\Olympiad\Actions;

use App\Domain\Olympiad\DataTransferObjects\OlympiadThemeRequest;
use App\Domain\Olympiad\DataTransferObjects\OlympiadThemeResult;
use App\Domain\Olympiad\Exceptions\OlympiadThemeNotFoundException;
use App\Domain\Olympiad\Models\OlympiadQuestion;
use App\Domain\Olympiad\Support\SeededShuffler;
use Illuminate\Support\Collection;

final class FetchOlympiadThemeQuestionsAction
{
    public function __construct(private SeededShuffler $shuffler) {}

    public function execute(OlympiadThemeRequest $request): OlympiadThemeResult
    {
        $questions = OlympiadQuestion::query()
            ->forLanguage($request->language)
            ->forBook($request->book)
            ->forChapterRange($request->range)
            ->with('answers')
            ->get();

        if ($questions->isEmpty()) {
            throw new OlympiadThemeNotFoundException;
        }

        $seed = $request->seed ?? random_int(1, PHP_INT_MAX);

        $ordered = $this->shuffler->shuffle($questions->all(), $seed);

        foreach ($ordered as $question) {
            $shuffledAnswers = $this->shuffler->shuffle($question->answers->all(), $seed);
            $question->setRelation('answers', Collection::make($shuffledAnswers));
        }

        /** @var Collection<int, OlympiadQuestion> $collection */
        $collection = Collection::make($ordered);

        return new OlympiadThemeResult($collection, $seed);
    }
}

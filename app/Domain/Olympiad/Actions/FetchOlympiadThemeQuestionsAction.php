<?php

declare(strict_types=1);

namespace App\Domain\Olympiad\Actions;

use App\Domain\Olympiad\DataTransferObjects\OlympiadThemeRequest;
use App\Domain\Olympiad\DataTransferObjects\OlympiadThemeResult;
use App\Domain\Olympiad\Exceptions\OlympiadThemeNotFoundException;
use App\Domain\Olympiad\Models\OlympiadAnswer;
use App\Domain\Olympiad\Models\OlympiadQuestion;
use App\Domain\Olympiad\Support\OlympiadCacheKeys;
use App\Domain\Olympiad\Support\SeededShuffler;
use App\Support\Caching\CachedRead;
use Illuminate\Support\Collection;

final class FetchOlympiadThemeQuestionsAction
{
    public function __construct(
        private SeededShuffler $shuffler,
        private CachedRead $cache,
    ) {}

    public function execute(OlympiadThemeRequest $request): OlympiadThemeResult
    {
        $rawQuestions = $this->loadThemeQuestions($request);

        if ($rawQuestions === []) {
            throw new OlympiadThemeNotFoundException;
        }

        $seed = $request->seed ?? random_int(1, PHP_INT_MAX);

        $orderedRaw = $this->shuffler->shuffle($rawQuestions, $seed);

        $questions = [];
        foreach ($orderedRaw as $row) {
            $question = (new OlympiadQuestion)->forceFill([
                'id' => $row['id'],
                'question' => $row['question'],
                'explanation' => $row['explanation'],
            ]);
            $question->exists = true;

            $shuffledAnswers = $this->shuffler->shuffle($row['answers'], $seed);

            $answers = [];
            foreach ($shuffledAnswers as $answerRow) {
                $answer = (new OlympiadAnswer)->forceFill([
                    'id' => $answerRow['id'],
                    'text' => $answerRow['text'],
                    'is_correct' => $answerRow['is_correct'],
                ]);
                $answer->exists = true;
                $answers[] = $answer;
            }

            $question->setRelation('answers', Collection::make($answers));
            $questions[] = $question;
        }

        /** @var Collection<int, OlympiadQuestion> $collection */
        $collection = Collection::make($questions);

        return new OlympiadThemeResult($collection, $seed);
    }

    /**
     * Cached unshuffled question set for the theme. The shape is plain
     * arrays (not Eloquent models) so the cached payload survives the
     * Redis serializer + the `serializable_classes => false` allowlist.
     *
     * @return array<int, array{
     *     id: int,
     *     question: string,
     *     explanation: ?string,
     *     answers: array<int, array{id: int, text: string, is_correct: bool}>
     * }>
     */
    private function loadThemeQuestions(OlympiadThemeRequest $request): array
    {
        return $this->cache->read(
            OlympiadCacheKeys::themeQuestions($request->book, $request->range, $request->language),
            OlympiadCacheKeys::tagsForTheme($request->book, $request->range, $request->language),
            3600,
            static function () use ($request): array {
                $questions = OlympiadQuestion::query()
                    ->forLanguage($request->language)
                    ->forBook($request->book)
                    ->forChapterRange($request->range)
                    ->with('answers')
                    ->get();

                $rows = [];
                foreach ($questions as $question) {
                    $answers = [];
                    foreach ($question->answers as $answer) {
                        $answers[] = [
                            'id' => (int) $answer->id,
                            'text' => (string) $answer->text,
                            'is_correct' => (bool) $answer->is_correct,
                        ];
                    }

                    $rows[] = [
                        'id' => (int) $question->id,
                        'question' => (string) $question->question,
                        'explanation' => $question->explanation,
                        'answers' => $answers,
                    ];
                }

                return $rows;
            },
        );
    }
}

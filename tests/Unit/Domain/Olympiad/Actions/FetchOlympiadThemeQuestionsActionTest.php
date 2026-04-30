<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Olympiad\Actions;

use App\Domain\Olympiad\Actions\FetchOlympiadThemeQuestionsAction;
use App\Domain\Olympiad\DataTransferObjects\OlympiadThemeRequest;
use App\Domain\Olympiad\Exceptions\OlympiadThemeNotFoundException;
use App\Domain\Olympiad\Models\OlympiadAnswer;
use App\Domain\Olympiad\Models\OlympiadQuestion;
use App\Domain\Olympiad\Support\SeededShuffler;
use App\Domain\Reference\ChapterRange;
use App\Domain\Shared\Enums\Language;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class FetchOlympiadThemeQuestionsActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_throws_when_theme_has_no_questions(): void
    {
        $this->expectException(OlympiadThemeNotFoundException::class);

        $action = app(FetchOlympiadThemeQuestionsAction::class);

        $action->execute(new OlympiadThemeRequest(
            book: 'GEN',
            range: new ChapterRange(1, 3),
            language: Language::En,
            seed: 1,
        ));
    }

    public function test_same_seed_yields_identical_question_and_answer_order(): void
    {
        $this->seedTheme('GEN', 1, 3, Language::En, 12);

        $action = app(FetchOlympiadThemeQuestionsAction::class);

        $request = new OlympiadThemeRequest(
            book: 'GEN',
            range: new ChapterRange(1, 3),
            language: Language::En,
            seed: 424242,
        );

        $first = $action->execute($request);
        $second = $action->execute($request);

        $this->assertSame(
            $first->questions->pluck('id')->all(),
            $second->questions->pluck('id')->all(),
        );

        foreach ($first->questions as $index => $q) {
            $counterpart = $second->questions[$index];
            $this->assertNotNull($counterpart);
            $this->assertSame(
                $q->answers->pluck('id')->all(),
                $counterpart->answers->pluck('id')->all(),
            );
        }
    }

    public function test_different_seeds_yield_different_question_order(): void
    {
        $this->seedTheme('GEN', 1, 3, Language::En, 12);

        $action = app(FetchOlympiadThemeQuestionsAction::class);

        $a = $action->execute(new OlympiadThemeRequest('GEN', new ChapterRange(1, 3), Language::En, 1));
        $b = $action->execute(new OlympiadThemeRequest('GEN', new ChapterRange(1, 3), Language::En, 9_999_999));

        $this->assertNotSame(
            $a->questions->pluck('id')->all(),
            $b->questions->pluck('id')->all(),
        );
    }

    public function test_canonical_order_follows_position_before_shuffle(): void
    {
        // Bind an identity shuffler so the canonical question ordering is
        // observable directly. Questions inserted in id order but with
        // mixed positions must come out sorted by `(position, id)`.
        $this->app->instance(SeededShuffler::class, new class extends SeededShuffler
        {
            public function shuffle(array $items, int $seed): array
            {
                return array_values($items);
            }
        });

        $first = OlympiadQuestion::factory()->forTheme('GEN', 1, 3, Language::En)->create(['position' => 3]);
        $second = OlympiadQuestion::factory()->forTheme('GEN', 1, 3, Language::En)->create(['position' => 1]);
        $third = OlympiadQuestion::factory()->forTheme('GEN', 1, 3, Language::En)->create(['position' => 2]);

        $action = app(FetchOlympiadThemeQuestionsAction::class);

        $result = $action->execute(new OlympiadThemeRequest(
            book: 'GEN',
            range: new ChapterRange(1, 3),
            language: Language::En,
            seed: 1,
        ));

        $this->assertSame(
            [$second->id, $third->id, $first->id],
            $result->questions->pluck('id')->all(),
        );
    }

    public function test_generates_and_returns_seed_when_omitted(): void
    {
        $this->seedTheme('GEN', 1, 3, Language::En, 3);

        $action = app(FetchOlympiadThemeQuestionsAction::class);

        $result = $action->execute(new OlympiadThemeRequest(
            book: 'GEN',
            range: new ChapterRange(1, 3),
            language: Language::En,
            seed: null,
        ));

        $this->assertGreaterThanOrEqual(1, $result->seed);
        $this->assertCount(3, $result->questions);
    }

    private function seedTheme(string $book, int $from, int $to, Language $language, int $count): void
    {
        OlympiadQuestion::factory()
            ->forTheme($book, $from, $to, $language)
            ->count($count)
            ->create()
            ->each(function (OlympiadQuestion $question): void {
                OlympiadAnswer::factory()
                    ->count(4)
                    ->sequence(
                        ['position' => 0, 'is_correct' => true],
                        ['position' => 1, 'is_correct' => false],
                        ['position' => 2, 'is_correct' => false],
                        ['position' => 3, 'is_correct' => false],
                    )
                    ->create(['olympiad_question_id' => $question->id]);
            });
    }
}

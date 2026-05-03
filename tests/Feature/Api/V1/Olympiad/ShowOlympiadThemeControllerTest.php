<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Olympiad;

use App\Domain\Olympiad\Models\OlympiadAnswer;
use App\Domain\Olympiad\Models\OlympiadQuestion;
use App\Domain\Shared\Enums\Language;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithApiKeyClient;
use Tests\TestCase;

final class ShowOlympiadThemeControllerTest extends TestCase
{
    use RefreshDatabase;
    use WithApiKeyClient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpApiKeyClient();
    }

    public function test_it_rejects_missing_api_key(): void
    {
        $this->getJson(route('olympiad.themes.show', ['book' => 'GEN', 'chapters' => '1-3']))
            ->assertUnauthorized();
    }

    public function test_it_returns_questions_with_answers_and_seed_meta(): void
    {
        $this->seedTheme('GEN', 1, 3, Language::En, 5);

        $response = $this->withHeader('X-Api-Key', 'mobile-valid-key')
            ->getJson(route('olympiad.themes.show', ['book' => 'GEN', 'chapters' => '1-3']))
            ->assertOk();

        $response->assertJsonStructure([
            'data' => [
                [
                    'id',
                    'uuid',
                    'verse',
                    'chapter',
                    'is_reviewed',
                    'question',
                    'explanation',
                    'answers' => [['id', 'uuid', 'text', 'is_correct']],
                ],
            ],
            'meta' => ['seed'],
        ]);

        $this->assertCount(5, $response->json('data'));
        $this->assertIsInt($response->json('meta.seed'));

        // Question + answer UUIDs must be exposed so clients can drive the
        // attempt-submission flow (POST /olympiad/attempts/{attempt}/answers).
        foreach ($response->json('data') as $question) {
            $this->assertIsString($question['uuid']);
            $this->assertNotEmpty($question['uuid']);
            foreach ($question['answers'] as $answer) {
                $this->assertIsString($answer['uuid']);
                $this->assertNotEmpty($answer['uuid']);
            }
        }
    }

    public function test_same_seed_replays_identical_ordering(): void
    {
        $this->seedTheme('GEN', 1, 3, Language::En, 8);

        $first = $this->withHeader('X-Api-Key', 'mobile-valid-key')
            ->getJson(route('olympiad.themes.show', ['book' => 'GEN', 'chapters' => '1-3', 'seed' => 42]))
            ->assertOk();

        $second = $this->withHeader('X-Api-Key', 'mobile-valid-key')
            ->getJson(route('olympiad.themes.show', ['book' => 'GEN', 'chapters' => '1-3', 'seed' => 42]))
            ->assertOk();

        $this->assertSame(42, $first->json('meta.seed'));
        $this->assertSame(42, $second->json('meta.seed'));

        $firstQuestionIds = array_column($first->json('data'), 'id');
        $secondQuestionIds = array_column($second->json('data'), 'id');
        $this->assertSame($firstQuestionIds, $secondQuestionIds);

        // Verify answer ordering is stable too
        $firstAnswerIds = array_column($first->json('data.0.answers'), 'id');
        $secondAnswerIds = array_column($second->json('data.0.answers'), 'id');
        $this->assertSame($firstAnswerIds, $secondAnswerIds);
    }

    public function test_it_returns_404_for_missing_theme(): void
    {
        $this->withHeader('X-Api-Key', 'mobile-valid-key')
            ->getJson(route('olympiad.themes.show', ['book' => 'GEN', 'chapters' => '1-3']))
            ->assertNotFound()
            ->assertJsonStructure(['message']);
    }

    public function test_it_does_not_fall_back_to_another_language(): void
    {
        // Theme only exists in English; request asks for Hungarian.
        $this->seedTheme('GEN', 1, 3, Language::En, 3);

        $this->withHeader('X-Api-Key', 'mobile-valid-key')
            ->getJson(route('olympiad.themes.show', [
                'book' => 'GEN',
                'chapters' => '1-3',
                'language' => 'hu',
            ]))
            ->assertNotFound();
    }

    public function test_single_chapter_segment_is_supported(): void
    {
        $this->seedTheme('GEN', 5, 5, Language::En, 2);

        $this->withHeader('X-Api-Key', 'mobile-valid-key')
            ->getJson(route('olympiad.themes.show', ['book' => 'GEN', 'chapters' => '5']))
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_malformed_chapters_segment_returns_422(): void
    {
        $this->withHeader('X-Api-Key', 'mobile-valid-key')
            ->getJson(route('olympiad.themes.show', ['book' => 'GEN', 'chapters' => 'bad']))
            ->assertUnprocessable()
            ->assertJsonStructure(['message', 'errors' => ['reference']]);
    }

    public function test_inverted_chapter_range_returns_422(): void
    {
        $this->withHeader('X-Api-Key', 'mobile-valid-key')
            ->getJson(route('olympiad.themes.show', ['book' => 'GEN', 'chapters' => '5-3']))
            ->assertUnprocessable();
    }

    public function test_unknown_book_is_rejected_as_validation_error(): void
    {
        $this->withHeader('X-Api-Key', 'mobile-valid-key')
            ->getJson(route('olympiad.themes.show', ['book' => 'XXX', 'chapters' => '1-3']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['book']);
    }

    public function test_cache_control_header_is_set_on_success(): void
    {
        $this->seedTheme('GEN', 1, 3, Language::En, 1);

        $response = $this->withHeader('X-Api-Key', 'mobile-valid-key')
            ->getJson(route('olympiad.themes.show', ['book' => 'GEN', 'chapters' => '1-3']))
            ->assertOk();

        $this->assertSame('max-age=3600, public', $response->headers->get('Cache-Control'));
    }

    private function seedTheme(string $book, int $from, int $to, Language $language, int $questionCount): void
    {
        OlympiadQuestion::factory()
            ->forTheme($book, $from, $to, $language)
            ->count($questionCount)
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

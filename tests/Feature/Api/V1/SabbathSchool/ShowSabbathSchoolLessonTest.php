<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\SabbathSchool;

use App\Domain\SabbathSchool\Models\SabbathSchoolLesson;
use App\Domain\SabbathSchool\Models\SabbathSchoolQuestion;
use App\Domain\SabbathSchool\Models\SabbathSchoolSegment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\WithApiKeyClient;
use Tests\TestCase;

final class ShowSabbathSchoolLessonTest extends TestCase
{
    use RefreshDatabase;
    use WithApiKeyClient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpApiKeyClient();
    }

    public function test_it_returns_the_lesson_with_segments_and_questions(): void
    {
        $lesson = SabbathSchoolLesson::factory()->create();
        $segment = SabbathSchoolSegment::factory()->forLesson($lesson)->atPosition(0)->create();
        $question = SabbathSchoolQuestion::factory()->forSegment($segment)->atPosition(0)->create();

        $this->withHeaders($this->apiKeyHeaders())
            ->getJson(route('sabbath-school.lessons.show', ['lesson' => $lesson->id]))
            ->assertOk()
            ->assertJsonPath('data.id', $lesson->id)
            ->assertJsonPath('data.segments.0.id', $segment->id)
            ->assertJsonPath('data.segments.0.questions.0.id', $question->id)
            ->assertJsonPath('data.segments.0.questions.0.prompt', $question->prompt);
    }

    public function test_it_avoids_n_plus_one_on_a_large_fixture(): void
    {
        $lesson = SabbathSchoolLesson::factory()->create();

        for ($day = 0; $day < 7; $day++) {
            $segment = SabbathSchoolSegment::factory()
                ->forLesson($lesson)
                ->atPosition($day)
                ->create();

            SabbathSchoolQuestion::factory()
                ->count(5)
                ->state(fn (): array => ['sabbath_school_segment_id' => $segment->id])
                ->create();
        }

        DB::enableQueryLog();

        $response = $this->withHeaders($this->apiKeyHeaders())
            ->getJson(route('sabbath-school.lessons.show', ['lesson' => $lesson->id]))
            ->assertOk();

        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $response->assertJsonCount(7, 'data.segments');
        $response->assertJsonCount(5, 'data.segments.0.questions');

        // 1 for the lesson, 1 for segments, 1 for questions. Plus token/auth
        // reads by sanctum. Cap well below a per-segment fan-out (1 + 7*N).
        $this->assertLessThanOrEqual(
            8,
            $queryCount,
            "Expected the lesson detail to avoid N+1; got {$queryCount} queries.",
        );
    }

    public function test_it_returns_404_for_unpublished_lessons(): void
    {
        $lesson = SabbathSchoolLesson::factory()->draft()->create();

        $this->withHeaders($this->apiKeyHeaders())
            ->getJson(route('sabbath-school.lessons.show', ['lesson' => $lesson->id]))
            ->assertNotFound();
    }

    public function test_it_sets_public_cache_headers(): void
    {
        $lesson = SabbathSchoolLesson::factory()->create();

        $response = $this->withHeaders($this->apiKeyHeaders())
            ->getJson(route('sabbath-school.lessons.show', ['lesson' => $lesson->id]));

        $response->assertOk();
        $cacheControl = (string) $response->headers->get('Cache-Control');
        $this->assertStringContainsString('public', $cacheControl);
        $this->assertStringContainsString('max-age=3600', $cacheControl);
    }

    public function test_it_rejects_missing_credentials(): void
    {
        $lesson = SabbathSchoolLesson::factory()->create();

        $this->getJson(route('sabbath-school.lessons.show', ['lesson' => $lesson->id]))
            ->assertUnauthorized();
    }
}

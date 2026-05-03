<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\SabbathSchool;

use App\Domain\SabbathSchool\Models\SabbathSchoolFavorite;
use App\Domain\SabbathSchool\Models\SabbathSchoolLesson;
use App\Domain\SabbathSchool\Models\SabbathSchoolSegment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAuthentication;
use Tests\TestCase;

final class SabbathSchoolFavoriteTest extends TestCase
{
    use InteractsWithAuthentication;
    use RefreshDatabase;

    public function test_toggle_creates_whole_lesson_favorite_when_segment_id_is_omitted(): void
    {
        $user = $this->givenAnAuthenticatedUser();
        $lesson = SabbathSchoolLesson::factory()->create();

        $this->postJson(route('sabbath-school.favorites.toggle'), [
            'lesson_id' => $lesson->id,
        ])
            ->assertCreated()
            ->assertJsonPath('data.lesson_id', $lesson->id)
            ->assertJsonPath('data.segment_id', null)
            ->assertJsonPath('data.whole_lesson', true);

        $this->assertDatabaseHas('sabbath_school_favorites', [
            'user_id' => $user->id,
            'sabbath_school_lesson_id' => $lesson->id,
            'sabbath_school_segment_id' => null,
        ]);
    }

    public function test_toggle_removes_whole_lesson_favorite_on_second_call(): void
    {
        $user = $this->givenAnAuthenticatedUser();
        $lesson = SabbathSchoolLesson::factory()->create();

        SabbathSchoolFavorite::factory()
            ->forUser($user)
            ->forLesson($lesson)
            ->wholeLesson()
            ->create();

        $this->postJson(route('sabbath-school.favorites.toggle'), [
            'lesson_id' => $lesson->id,
        ])
            ->assertOk()
            ->assertExactJson(['deleted' => true]);

        $this->assertSoftDeleted('sabbath_school_favorites', [
            'user_id' => $user->id,
            'sabbath_school_lesson_id' => $lesson->id,
        ]);
    }

    public function test_whole_lesson_and_per_segment_favorites_coexist(): void
    {
        $user = $this->givenAnAuthenticatedUser();
        $lesson = SabbathSchoolLesson::factory()->create();
        $segment = SabbathSchoolSegment::factory()->forLesson($lesson)->create();

        SabbathSchoolFavorite::factory()
            ->forUser($user)
            ->forLesson($lesson)
            ->wholeLesson()
            ->create();

        $this->postJson(route('sabbath-school.favorites.toggle'), [
            'lesson_id' => $lesson->id,
            'segment_id' => $segment->id,
        ])
            ->assertCreated()
            ->assertJsonPath('data.segment_id', $segment->id)
            ->assertJsonPath('data.whole_lesson', false);

        $this->assertDatabaseCount('sabbath_school_favorites', 2);
        $this->assertDatabaseHas('sabbath_school_favorites', [
            'user_id' => $user->id,
            'sabbath_school_lesson_id' => $lesson->id,
            'sabbath_school_segment_id' => null,
        ]);
        $this->assertDatabaseHas('sabbath_school_favorites', [
            'user_id' => $user->id,
            'sabbath_school_lesson_id' => $lesson->id,
            'sabbath_school_segment_id' => $segment->id,
        ]);
    }

    public function test_toggle_rejects_segment_from_a_different_lesson(): void
    {
        $this->givenAnAuthenticatedUser();
        $lesson = SabbathSchoolLesson::factory()->create();
        $otherLesson = SabbathSchoolLesson::factory()->create();
        $otherSegment = SabbathSchoolSegment::factory()->forLesson($otherLesson)->create();

        $this->postJson(route('sabbath-school.favorites.toggle'), [
            'lesson_id' => $lesson->id,
            'segment_id' => $otherSegment->id,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('segment_id');
    }

    public function test_toggle_rejects_missing_lesson_id(): void
    {
        $this->givenAnAuthenticatedUser();

        $this->postJson(route('sabbath-school.favorites.toggle'), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('lesson_id');
    }

    public function test_toggle_rejects_unknown_lesson_id(): void
    {
        $this->givenAnAuthenticatedUser();

        $this->postJson(route('sabbath-school.favorites.toggle'), [
            'lesson_id' => 999_999,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('lesson_id');
    }

    public function test_list_returns_callers_favorites(): void
    {
        $user = $this->givenAnAuthenticatedUser();
        $lesson = SabbathSchoolLesson::factory()->create();

        SabbathSchoolFavorite::factory()
            ->forUser($user)
            ->forLesson($lesson)
            ->wholeLesson()
            ->create();

        $otherLesson = SabbathSchoolLesson::factory()->create();
        SabbathSchoolFavorite::factory()->forLesson($otherLesson)->wholeLesson()->create(); // different user

        $this->getJson(route('sabbath-school.favorites.index'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.lesson_id', $lesson->id);
    }

    public function test_favorite_endpoints_require_sanctum(): void
    {
        $lesson = SabbathSchoolLesson::factory()->create();

        $this->postJson(route('sabbath-school.favorites.toggle'), ['lesson_id' => $lesson->id])
            ->assertUnauthorized();
        $this->getJson(route('sabbath-school.favorites.index'))
            ->assertUnauthorized();
    }
}

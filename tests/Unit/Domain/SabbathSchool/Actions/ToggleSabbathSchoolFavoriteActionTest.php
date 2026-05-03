<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\SabbathSchool\Actions;

use App\Domain\SabbathSchool\Actions\ToggleSabbathSchoolFavoriteAction;
use App\Domain\SabbathSchool\DataTransferObjects\ToggleSabbathSchoolFavoriteData;
use App\Domain\SabbathSchool\Models\SabbathSchoolFavorite;
use App\Domain\SabbathSchool\Models\SabbathSchoolLesson;
use App\Domain\SabbathSchool\Models\SabbathSchoolSegment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ToggleSabbathSchoolFavoriteActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_inserts_whole_lesson_favorite_with_null_segment_id(): void
    {
        $user = User::factory()->create();
        $lesson = SabbathSchoolLesson::factory()->create();

        $result = $this->app->make(ToggleSabbathSchoolFavoriteAction::class)->execute(
            new ToggleSabbathSchoolFavoriteData($user, $lesson->id, null),
        );

        $this->assertTrue($result->created);
        $this->assertNotNull($result->favorite);
        $this->assertDatabaseHas('sabbath_school_favorites', [
            'user_id' => $user->id,
            'sabbath_school_lesson_id' => $lesson->id,
            'sabbath_school_segment_id' => null,
        ]);
    }

    public function test_it_deletes_the_whole_lesson_row_on_second_call(): void
    {
        $user = User::factory()->create();
        $lesson = SabbathSchoolLesson::factory()->create();

        SabbathSchoolFavorite::factory()
            ->forUser($user)
            ->forLesson($lesson)
            ->wholeLesson()
            ->create();

        $result = $this->app->make(ToggleSabbathSchoolFavoriteAction::class)->execute(
            new ToggleSabbathSchoolFavoriteData($user, $lesson->id, null),
        );

        $this->assertFalse($result->created);
        $this->assertSoftDeleted('sabbath_school_favorites', [
            'user_id' => $user->id,
            'sabbath_school_lesson_id' => $lesson->id,
        ]);
    }

    public function test_segment_level_insert_does_not_touch_the_whole_lesson_row(): void
    {
        $user = User::factory()->create();
        $lesson = SabbathSchoolLesson::factory()->create();
        $segment = SabbathSchoolSegment::factory()->forLesson($lesson)->create();

        SabbathSchoolFavorite::factory()
            ->forUser($user)
            ->forLesson($lesson)
            ->wholeLesson()
            ->create();

        $result = $this->app->make(ToggleSabbathSchoolFavoriteAction::class)->execute(
            new ToggleSabbathSchoolFavoriteData($user, $lesson->id, $segment->id),
        );

        $this->assertTrue($result->created);
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

    public function test_re_toggling_restores_the_same_primary_key(): void
    {
        $user = User::factory()->create();
        $lesson = SabbathSchoolLesson::factory()->create();

        $action = $this->app->make(ToggleSabbathSchoolFavoriteAction::class);
        $dto = new ToggleSabbathSchoolFavoriteData($user, $lesson->id, null);

        $created = $action->execute($dto);
        $this->assertNotNull($created->favorite);
        $originalId = $created->favorite->id;

        $action->execute($dto); // soft-delete

        $restored = $action->execute($dto); // restore

        $this->assertTrue($restored->created);
        $this->assertNotNull($restored->favorite);
        $this->assertSame($originalId, $restored->favorite->id);
        $this->assertDatabaseCount('sabbath_school_favorites', 1);
    }
}

<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\SabbathSchool\Models\SabbathSchoolFavorite;
use App\Domain\SabbathSchool\Models\SabbathSchoolLesson;
use App\Domain\SabbathSchool\Models\SabbathSchoolSegment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SabbathSchoolFavorite>
 */
final class SabbathSchoolFavoriteFactory extends Factory
{
    protected $model = SabbathSchoolFavorite::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'sabbath_school_lesson_id' => SabbathSchoolLesson::factory(),
            'sabbath_school_segment_id' => null,
        ];
    }

    public function forUser(User $user): self
    {
        return $this->state(fn (): array => [
            'user_id' => $user->id,
        ]);
    }

    public function forLesson(SabbathSchoolLesson $lesson): self
    {
        return $this->state(fn (): array => [
            'sabbath_school_lesson_id' => $lesson->id,
        ]);
    }

    public function wholeLesson(): self
    {
        return $this->state(fn (): array => [
            'sabbath_school_segment_id' => null,
        ]);
    }

    public function forSegment(SabbathSchoolSegment $segment): self
    {
        return $this->state(fn (): array => [
            'sabbath_school_segment_id' => $segment->id,
            'sabbath_school_lesson_id' => $segment->sabbath_school_lesson_id,
        ]);
    }

    public function forSegmentId(int $segmentId): self
    {
        return $this->state(fn (): array => [
            'sabbath_school_segment_id' => $segmentId,
        ]);
    }
}

<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\SabbathSchool\Models\SabbathSchoolLesson;
use App\Domain\SabbathSchool\Models\SabbathSchoolSegment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SabbathSchoolSegment>
 */
final class SabbathSchoolSegmentFactory extends Factory
{
    protected $model = SabbathSchoolSegment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sabbath_school_lesson_id' => SabbathSchoolLesson::factory(),
            'day' => fake()->numberBetween(0, 6),
            'title' => fake()->sentence(3),
            'content' => '<p>' . fake()->paragraph() . '</p>',
            'passages' => ['GEN.1:1.VDC'],
            'position' => 0,
        ];
    }

    public function forLesson(SabbathSchoolLesson $lesson): self
    {
        return $this->state(fn (): array => [
            'sabbath_school_lesson_id' => $lesson->id,
        ]);
    }

    public function atPosition(int $position): self
    {
        return $this->state(fn (): array => [
            'position' => $position,
            'day' => $position % 7,
        ]);
    }
}

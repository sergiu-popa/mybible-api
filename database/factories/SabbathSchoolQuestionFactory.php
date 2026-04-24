<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\SabbathSchool\Models\SabbathSchoolQuestion;
use App\Domain\SabbathSchool\Models\SabbathSchoolSegment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SabbathSchoolQuestion>
 */
final class SabbathSchoolQuestionFactory extends Factory
{
    protected $model = SabbathSchoolQuestion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sabbath_school_segment_id' => SabbathSchoolSegment::factory(),
            'position' => 0,
            'prompt' => fake()->sentence(8) . '?',
        ];
    }

    public function forSegment(SabbathSchoolSegment $segment): self
    {
        return $this->state(fn (): array => [
            'sabbath_school_segment_id' => $segment->id,
        ]);
    }

    public function atPosition(int $position): self
    {
        return $this->state(fn (): array => [
            'position' => $position,
        ]);
    }
}

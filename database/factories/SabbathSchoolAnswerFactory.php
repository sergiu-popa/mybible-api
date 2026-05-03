<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\SabbathSchool\Models\SabbathSchoolAnswer;
use App\Domain\SabbathSchool\Models\SabbathSchoolSegmentContent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SabbathSchoolAnswer>
 */
final class SabbathSchoolAnswerFactory extends Factory
{
    protected $model = SabbathSchoolAnswer::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'segment_content_id' => SabbathSchoolSegmentContent::factory()->question(),
            'content' => fake()->paragraph(),
        ];
    }

    public function forUser(User $user): self
    {
        return $this->state(fn (): array => [
            'user_id' => $user->id,
        ]);
    }

    public function forSegmentContent(SabbathSchoolSegmentContent $content): self
    {
        return $this->state(fn (): array => [
            'segment_content_id' => $content->id,
        ]);
    }
}

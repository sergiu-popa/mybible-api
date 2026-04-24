<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\SabbathSchool\Models\SabbathSchoolAnswer;
use App\Domain\SabbathSchool\Models\SabbathSchoolQuestion;
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
            'sabbath_school_question_id' => SabbathSchoolQuestion::factory(),
            'content' => fake()->paragraph(),
        ];
    }

    public function forUser(User $user): self
    {
        return $this->state(fn (): array => [
            'user_id' => $user->id,
        ]);
    }

    public function forQuestion(SabbathSchoolQuestion $question): self
    {
        return $this->state(fn (): array => [
            'sabbath_school_question_id' => $question->id,
        ]);
    }
}

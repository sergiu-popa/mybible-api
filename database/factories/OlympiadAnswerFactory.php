<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Olympiad\Models\OlympiadAnswer;
use App\Domain\Olympiad\Models\OlympiadQuestion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OlympiadAnswer>
 */
final class OlympiadAnswerFactory extends Factory
{
    protected $model = OlympiadAnswer::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'olympiad_question_id' => OlympiadQuestion::factory(),
            'text' => fake()->sentence(),
            'is_correct' => false,
            'position' => 0,
        ];
    }

    public function correct(): self
    {
        return $this->state(fn (): array => ['is_correct' => true]);
    }

    public function incorrect(): self
    {
        return $this->state(fn (): array => ['is_correct' => false]);
    }
}

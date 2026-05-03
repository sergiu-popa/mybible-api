<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Olympiad\Models\OlympiadAttempt;
use App\Domain\Olympiad\Models\OlympiadAttemptAnswer;
use App\Domain\Olympiad\Models\OlympiadQuestion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OlympiadAttemptAnswer>
 */
final class OlympiadAttemptAnswerFactory extends Factory
{
    protected $model = OlympiadAttemptAnswer::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'attempt_id' => OlympiadAttempt::factory(),
            'olympiad_question_id' => OlympiadQuestion::factory(),
            'selected_answer_id' => null,
            'is_correct' => false,
        ];
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Olympiad\Attempts;

use App\Domain\Olympiad\Models\OlympiadAttempt;
use App\Domain\Olympiad\Models\OlympiadAttemptAnswer;
use App\Domain\Olympiad\Models\OlympiadQuestion;
use App\Domain\Shared\Enums\Language;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class FinishOlympiadAttemptTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_computes_score_from_correct_answers(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;
        $this->withHeader('Authorization', 'Bearer ' . $token);

        $attempt = OlympiadAttempt::factory()->forUser($user)->inProgress()->create([
            'book' => 'GEN',
            'chapters_label' => '1-3',
            'language' => Language::Ro,
            'total' => 3,
        ]);

        $q1 = OlympiadQuestion::factory()->forTheme('GEN', 1, 3, Language::Ro)->create();
        $q2 = OlympiadQuestion::factory()->forTheme('GEN', 1, 3, Language::Ro)->create();
        $q3 = OlympiadQuestion::factory()->forTheme('GEN', 1, 3, Language::Ro)->create();

        OlympiadAttemptAnswer::factory()->create([
            'attempt_id' => $attempt->id,
            'olympiad_question_id' => $q1->id,
            'is_correct' => true,
        ]);
        OlympiadAttemptAnswer::factory()->create([
            'attempt_id' => $attempt->id,
            'olympiad_question_id' => $q2->id,
            'is_correct' => true,
        ]);
        OlympiadAttemptAnswer::factory()->create([
            'attempt_id' => $attempt->id,
            'olympiad_question_id' => $q3->id,
            'is_correct' => false,
        ]);

        $this->postJson(route('olympiad.attempts.finish', ['attempt' => $attempt->id]))
            ->assertOk()
            ->assertJsonPath('data.score', 2)
            ->assertJsonPath('data.total', 3);
    }

    public function test_it_rejects_second_finish(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;
        $this->withHeader('Authorization', 'Bearer ' . $token);

        $attempt = OlympiadAttempt::factory()->forUser($user)->create([
            'completed_at' => CarbonImmutable::now()->subMinute(),
        ]);

        $this->postJson(route('olympiad.attempts.finish', ['attempt' => $attempt->id]))
            ->assertUnprocessable();
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Olympiad\Attempts;

use App\Domain\Olympiad\Models\OlympiadAnswer;
use App\Domain\Olympiad\Models\OlympiadAttempt;
use App\Domain\Olympiad\Models\OlympiadAttemptAnswer;
use App\Domain\Olympiad\Models\OlympiadQuestion;
use App\Domain\Shared\Enums\Language;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SubmitOlympiadAttemptAnswersTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private OlympiadAttempt $attempt;

    private OlympiadQuestion $question;

    private OlympiadAnswer $correct;

    private OlympiadAnswer $wrong;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $token = $this->user->createToken('test')->plainTextToken;
        $this->withHeader('Authorization', 'Bearer ' . $token);

        $this->question = OlympiadQuestion::factory()->forTheme('GEN', 1, 3, Language::Ro)->create();
        $this->correct = OlympiadAnswer::factory()->correct()->create([
            'olympiad_question_id' => $this->question->id,
            'position' => 1,
        ]);
        $this->wrong = OlympiadAnswer::factory()->incorrect()->create([
            'olympiad_question_id' => $this->question->id,
            'position' => 2,
        ]);

        $this->attempt = OlympiadAttempt::factory()->forUser($this->user)->inProgress()->create([
            'book' => 'GEN',
            'chapters_label' => '1-3',
            'language' => Language::Ro,
            'total' => 1,
        ]);
    }

    public function test_it_records_a_correct_answer(): void
    {
        $this->postJson(route('olympiad.attempts.answers.store', ['attempt' => $this->attempt->id]), [
            'answers' => [
                ['question_uuid' => $this->question->uuid, 'selected_answer_uuid' => $this->correct->uuid],
            ],
        ])->assertOk();

        $this->assertDatabaseHas('olympiad_attempt_answers', [
            'attempt_id' => $this->attempt->id,
            'olympiad_question_id' => $this->question->id,
            'selected_answer_id' => $this->correct->id,
            'is_correct' => 1,
        ]);
    }

    public function test_it_overwrites_idempotently(): void
    {
        $this->postJson(route('olympiad.attempts.answers.store', ['attempt' => $this->attempt->id]), [
            'answers' => [['question_uuid' => $this->question->uuid, 'selected_answer_uuid' => $this->wrong->uuid]],
        ])->assertOk();

        $this->postJson(route('olympiad.attempts.answers.store', ['attempt' => $this->attempt->id]), [
            'answers' => [['question_uuid' => $this->question->uuid, 'selected_answer_uuid' => $this->correct->uuid]],
        ])->assertOk();

        $this->assertSame(1, OlympiadAttemptAnswer::query()->where('attempt_id', $this->attempt->id)->count());
        $this->assertDatabaseHas('olympiad_attempt_answers', [
            'attempt_id' => $this->attempt->id,
            'olympiad_question_id' => $this->question->id,
            'selected_answer_id' => $this->correct->id,
            'is_correct' => 1,
        ]);
    }

    public function test_it_preserves_created_at_on_idempotent_resubmit(): void
    {
        $this->postJson(route('olympiad.attempts.answers.store', ['attempt' => $this->attempt->id]), [
            'answers' => [['question_uuid' => $this->question->uuid, 'selected_answer_uuid' => $this->wrong->uuid]],
        ])->assertOk();

        $row = OlympiadAttemptAnswer::query()
            ->where('attempt_id', $this->attempt->id)
            ->where('olympiad_question_id', $this->question->id)
            ->firstOrFail();
        $originalCreatedAt = $row->created_at;

        CarbonImmutable::setTestNow(CarbonImmutable::now()->addMinute());

        $this->postJson(route('olympiad.attempts.answers.store', ['attempt' => $this->attempt->id]), [
            'answers' => [['question_uuid' => $this->question->uuid, 'selected_answer_uuid' => $this->correct->uuid]],
        ])->assertOk();

        $refreshed = OlympiadAttemptAnswer::query()
            ->where('attempt_id', $this->attempt->id)
            ->where('olympiad_question_id', $this->question->id)
            ->firstOrFail();

        $this->assertTrue(
            $originalCreatedAt->equalTo($refreshed->created_at),
            'created_at must not advance on idempotent resubmit',
        );
        $this->assertTrue($refreshed->updated_at->greaterThan($originalCreatedAt));

        CarbonImmutable::setTestNow();
    }

    public function test_it_rejects_cross_theme_question_uuid(): void
    {
        $other = OlympiadQuestion::factory()->forTheme('JHN', 1, 3, Language::Ro)->create();

        $this->postJson(route('olympiad.attempts.answers.store', ['attempt' => $this->attempt->id]), [
            'answers' => [['question_uuid' => $other->uuid, 'selected_answer_uuid' => null]],
        ])->assertUnprocessable();
    }

    public function test_it_rejects_after_finish(): void
    {
        $this->attempt->update(['completed_at' => CarbonImmutable::now()]);

        $this->postJson(route('olympiad.attempts.answers.store', ['attempt' => $this->attempt->id]), [
            'answers' => [['question_uuid' => $this->question->uuid, 'selected_answer_uuid' => null]],
        ])->assertUnprocessable();
    }

    public function test_it_404s_for_other_users_attempt(): void
    {
        $other = User::factory()->create();
        $otherAttempt = OlympiadAttempt::factory()->forUser($other)->inProgress()->create();

        $this->postJson(route('olympiad.attempts.answers.store', ['attempt' => $otherAttempt->id]), [
            'answers' => [['question_uuid' => $this->question->uuid, 'selected_answer_uuid' => null]],
        ])->assertNotFound();
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\SabbathSchool\Actions;

use App\Domain\SabbathSchool\Actions\DeleteSabbathSchoolAnswerAction;
use App\Domain\SabbathSchool\Models\SabbathSchoolAnswer;
use App\Domain\SabbathSchool\Models\SabbathSchoolQuestion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class DeleteSabbathSchoolAnswerActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_deletes_the_answer_and_returns_true(): void
    {
        $user = User::factory()->create();
        $question = SabbathSchoolQuestion::factory()->create();

        SabbathSchoolAnswer::factory()
            ->forUser($user)
            ->forQuestion($question)
            ->create();

        $deleted = $this->app->make(DeleteSabbathSchoolAnswerAction::class)
            ->execute($user, $question);

        $this->assertTrue($deleted);
        $this->assertSoftDeleted('sabbath_school_answers', [
            'user_id' => $user->id,
            'sabbath_school_question_id' => $question->id,
        ]);
    }

    public function test_it_returns_false_when_no_answer_exists(): void
    {
        $user = User::factory()->create();
        $question = SabbathSchoolQuestion::factory()->create();

        $deleted = $this->app->make(DeleteSabbathSchoolAnswerAction::class)
            ->execute($user, $question);

        $this->assertFalse($deleted);
    }

    public function test_it_does_not_remove_another_users_answer(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $question = SabbathSchoolQuestion::factory()->create();

        $aliceAnswer = SabbathSchoolAnswer::factory()
            ->forUser($alice)
            ->forQuestion($question)
            ->create();

        $deleted = $this->app->make(DeleteSabbathSchoolAnswerAction::class)
            ->execute($bob, $question);

        $this->assertFalse($deleted);
        $this->assertDatabaseHas('sabbath_school_answers', [
            'id' => $aliceAnswer->id,
        ]);
    }
}

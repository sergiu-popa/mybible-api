<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\SabbathSchool;

use App\Domain\SabbathSchool\Models\SabbathSchoolAnswer;
use App\Domain\SabbathSchool\Models\SabbathSchoolLesson;
use App\Domain\SabbathSchool\Models\SabbathSchoolSegment;
use App\Domain\SabbathSchool\Models\SabbathSchoolSegmentContent;
use App\Http\Requests\SabbathSchool\UpsertSabbathSchoolAnswerRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAuthentication;
use Tests\TestCase;

final class SabbathSchoolAnswerTest extends TestCase
{
    use InteractsWithAuthentication;
    use RefreshDatabase;

    private function publishedQuestionContent(): SabbathSchoolSegmentContent
    {
        $lesson = SabbathSchoolLesson::factory()->published()->create();
        $segment = SabbathSchoolSegment::factory()->forLesson($lesson)->create();

        return SabbathSchoolSegmentContent::factory()->forSegment($segment)->question()->create();
    }

    public function test_it_creates_an_answer_on_first_post_with_201(): void
    {
        $user = $this->givenAnAuthenticatedUser();
        $content = $this->publishedQuestionContent();

        $this->postJson(
            route('sabbath-school.answers.upsert', ['content' => $content->id]),
            ['content' => 'First thoughts on the passage.'],
        )
            ->assertCreated()
            ->assertJsonPath('data.segment_content_id', $content->id)
            ->assertJsonPath('data.content', 'First thoughts on the passage.');

        $this->assertDatabaseHas('sabbath_school_answers', [
            'user_id' => $user->id,
            'segment_content_id' => $content->id,
            'content' => 'First thoughts on the passage.',
        ]);
    }

    public function test_it_overwrites_an_existing_answer_on_subsequent_post_with_200(): void
    {
        $user = $this->givenAnAuthenticatedUser();
        $content = $this->publishedQuestionContent();

        SabbathSchoolAnswer::factory()
            ->forUser($user)
            ->forSegmentContent($content)
            ->create(['content' => 'Old content.']);

        $this->postJson(
            route('sabbath-school.answers.upsert', ['content' => $content->id]),
            ['content' => 'New content.'],
        )
            ->assertOk()
            ->assertJsonPath('data.content', 'New content.');

        $this->assertDatabaseCount('sabbath_school_answers', 1);
        $this->assertDatabaseHas('sabbath_school_answers', [
            'user_id' => $user->id,
            'segment_content_id' => $content->id,
            'content' => 'New content.',
        ]);
    }

    public function test_it_rejects_non_question_content_blocks(): void
    {
        $this->givenAnAuthenticatedUser();
        $lesson = SabbathSchoolLesson::factory()->published()->create();
        $segment = SabbathSchoolSegment::factory()->forLesson($lesson)->create();
        $content = SabbathSchoolSegmentContent::factory()->forSegment($segment)->text()->create();

        $this->postJson(
            route('sabbath-school.answers.upsert', ['content' => $content->id]),
            ['content' => 'My answer.'],
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['content']);
    }

    public function test_it_rejects_content_exceeding_the_max_length(): void
    {
        $this->givenAnAuthenticatedUser();
        $content = $this->publishedQuestionContent();

        $this->postJson(
            route('sabbath-school.answers.upsert', ['content' => $content->id]),
            ['content' => str_repeat('a', UpsertSabbathSchoolAnswerRequest::CONTENT_MAX_LENGTH + 1)],
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['content']);
    }

    public function test_it_rejects_missing_content(): void
    {
        $this->givenAnAuthenticatedUser();
        $content = $this->publishedQuestionContent();

        $this->postJson(route('sabbath-school.answers.upsert', ['content' => $content->id]), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['content']);
    }

    public function test_it_rejects_save_on_draft_lessons(): void
    {
        $this->givenAnAuthenticatedUser();

        $lesson = SabbathSchoolLesson::factory()->draft()->create();
        $segment = SabbathSchoolSegment::factory()->forLesson($lesson)->create();
        $content = SabbathSchoolSegmentContent::factory()->forSegment($segment)->question()->create();

        $this->postJson(
            route('sabbath-school.answers.upsert', ['content' => $content->id]),
            ['content' => 'hi'],
        )->assertForbidden();

        $this->assertDatabaseCount('sabbath_school_answers', 0);
    }

    public function test_get_returns_the_callers_answer(): void
    {
        $user = $this->givenAnAuthenticatedUser();
        $content = $this->publishedQuestionContent();

        SabbathSchoolAnswer::factory()
            ->forUser($user)
            ->forSegmentContent($content)
            ->create(['content' => 'My answer.']);

        $this->getJson(route('sabbath-school.answers.show', ['content' => $content->id]))
            ->assertOk()
            ->assertJsonPath('data.content', 'My answer.');
    }

    public function test_get_returns_404_when_caller_has_no_answer(): void
    {
        $this->givenAnAuthenticatedUser();
        $content = $this->publishedQuestionContent();

        $this->getJson(route('sabbath-school.answers.show', ['content' => $content->id]))
            ->assertNotFound();
    }

    public function test_get_does_not_leak_other_users_answers(): void
    {
        $alice = User::factory()->create();
        $content = $this->publishedQuestionContent();

        SabbathSchoolAnswer::factory()
            ->forUser($alice)
            ->forSegmentContent($content)
            ->create(['content' => 'Alice content.']);

        $this->givenAnAuthenticatedUser();

        $this->getJson(route('sabbath-school.answers.show', ['content' => $content->id]))
            ->assertNotFound();
    }

    public function test_delete_removes_the_callers_answer_with_204(): void
    {
        $user = $this->givenAnAuthenticatedUser();
        $content = $this->publishedQuestionContent();

        SabbathSchoolAnswer::factory()
            ->forUser($user)
            ->forSegmentContent($content)
            ->create();

        $this->deleteJson(route('sabbath-school.answers.destroy', ['content' => $content->id]))
            ->assertNoContent();

        $this->assertSoftDeleted('sabbath_school_answers', [
            'user_id' => $user->id,
            'segment_content_id' => $content->id,
        ]);
    }

    public function test_delete_returns_404_when_caller_has_no_answer(): void
    {
        $this->givenAnAuthenticatedUser();
        $content = $this->publishedQuestionContent();

        $this->deleteJson(route('sabbath-school.answers.destroy', ['content' => $content->id]))
            ->assertNotFound();
    }

    public function test_delete_does_not_remove_another_users_answer(): void
    {
        $alice = User::factory()->create();
        $content = $this->publishedQuestionContent();

        SabbathSchoolAnswer::factory()
            ->forUser($alice)
            ->forSegmentContent($content)
            ->create(['content' => 'Alice content.']);

        $this->givenAnAuthenticatedUser();

        $this->deleteJson(route('sabbath-school.answers.destroy', ['content' => $content->id]))
            ->assertNotFound();

        $this->assertDatabaseHas('sabbath_school_answers', [
            'user_id' => $alice->id,
            'segment_content_id' => $content->id,
        ]);
    }

    public function test_upsert_does_not_overwrite_another_users_answer(): void
    {
        $alice = User::factory()->create();
        $content = $this->publishedQuestionContent();

        SabbathSchoolAnswer::factory()
            ->forUser($alice)
            ->forSegmentContent($content)
            ->create(['content' => 'Alice content.']);

        $bob = $this->givenAnAuthenticatedUser();

        $this->postJson(
            route('sabbath-school.answers.upsert', ['content' => $content->id]),
            ['content' => 'Bob content.'],
        )->assertCreated();

        $this->assertDatabaseHas('sabbath_school_answers', [
            'user_id' => $alice->id,
            'content' => 'Alice content.',
        ]);
        $this->assertDatabaseHas('sabbath_school_answers', [
            'user_id' => $bob->id,
            'content' => 'Bob content.',
        ]);
    }

    public function test_answer_endpoints_require_sanctum(): void
    {
        $content = $this->publishedQuestionContent();

        $this->getJson(route('sabbath-school.answers.show', ['content' => $content->id]))
            ->assertUnauthorized();
        $this->postJson(
            route('sabbath-school.answers.upsert', ['content' => $content->id]),
            ['content' => 'x'],
        )->assertUnauthorized();
        $this->deleteJson(route('sabbath-school.answers.destroy', ['content' => $content->id]))
            ->assertUnauthorized();
    }
}

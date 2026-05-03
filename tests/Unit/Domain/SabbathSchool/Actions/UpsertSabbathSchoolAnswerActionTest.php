<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\SabbathSchool\Actions;

use App\Domain\SabbathSchool\Actions\UpsertSabbathSchoolAnswerAction;
use App\Domain\SabbathSchool\DataTransferObjects\UpsertSabbathSchoolAnswerData;
use App\Domain\SabbathSchool\Models\SabbathSchoolAnswer;
use App\Domain\SabbathSchool\Models\SabbathSchoolSegmentContent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class UpsertSabbathSchoolAnswerActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_inserts_a_new_answer_when_none_exists(): void
    {
        $user = User::factory()->create();
        $content = SabbathSchoolSegmentContent::factory()->question()->create();

        $result = $this->app->make(UpsertSabbathSchoolAnswerAction::class)->execute(
            new UpsertSabbathSchoolAnswerData($user, $content, 'Hello.'),
        );

        $this->assertTrue($result->created);
        $this->assertSame('Hello.', $result->answer->content);
        $this->assertDatabaseHas('sabbath_school_answers', [
            'user_id' => $user->id,
            'segment_content_id' => $content->id,
            'content' => 'Hello.',
        ]);
    }

    public function test_it_overwrites_the_existing_answer(): void
    {
        $user = User::factory()->create();
        $content = SabbathSchoolSegmentContent::factory()->question()->create();

        SabbathSchoolAnswer::factory()
            ->forUser($user)
            ->forSegmentContent($content)
            ->create(['content' => 'Old.']);

        $result = $this->app->make(UpsertSabbathSchoolAnswerAction::class)->execute(
            new UpsertSabbathSchoolAnswerData($user, $content, 'New.'),
        );

        $this->assertFalse($result->created);
        $this->assertSame('New.', $result->answer->content);
        $this->assertDatabaseCount('sabbath_school_answers', 1);
    }

    public function test_it_does_not_overwrite_another_users_answer(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $content = SabbathSchoolSegmentContent::factory()->question()->create();

        SabbathSchoolAnswer::factory()
            ->forUser($alice)
            ->forSegmentContent($content)
            ->create(['content' => 'Alice.']);

        $result = $this->app->make(UpsertSabbathSchoolAnswerAction::class)->execute(
            new UpsertSabbathSchoolAnswerData($bob, $content, 'Bob.'),
        );

        $this->assertTrue($result->created);
        $this->assertDatabaseHas('sabbath_school_answers', [
            'user_id' => $alice->id,
            'content' => 'Alice.',
        ]);
        $this->assertDatabaseHas('sabbath_school_answers', [
            'user_id' => $bob->id,
            'content' => 'Bob.',
        ]);
    }
}

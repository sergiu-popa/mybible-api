<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\SabbathSchool\Actions;

use App\Domain\SabbathSchool\Actions\ToggleSabbathSchoolHighlightAction;
use App\Domain\SabbathSchool\DataTransferObjects\ToggleSabbathSchoolHighlightData;
use App\Domain\SabbathSchool\Models\SabbathSchoolHighlight;
use App\Domain\SabbathSchool\Models\SabbathSchoolSegmentContent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ToggleSabbathSchoolHighlightActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_inserts_a_highlight_when_none_exists(): void
    {
        $user = User::factory()->create();
        $content = SabbathSchoolSegmentContent::factory()->create();

        $result = $this->app->make(ToggleSabbathSchoolHighlightAction::class)->execute(
            new ToggleSabbathSchoolHighlightData($user, $content->id, 0, 16, '#FFEB3B'),
        );

        $this->assertTrue($result->created);
        $this->assertNotNull($result->highlight);
        $this->assertNull($result->highlight->passage);
        $this->assertDatabaseHas('sabbath_school_highlights', [
            'user_id' => $user->id,
            'segment_content_id' => $content->id,
            'start_position' => 0,
            'end_position' => 16,
            'color' => '#FFEB3B',
        ]);
    }

    public function test_it_deletes_the_highlight_on_identical_range(): void
    {
        $user = User::factory()->create();
        $content = SabbathSchoolSegmentContent::factory()->create();

        SabbathSchoolHighlight::factory()
            ->forUser($user)
            ->forSegmentContent($content)
            ->create([
                'start_position' => 0,
                'end_position' => 16,
                'color' => '#FFEB3B',
            ]);

        $result = $this->app->make(ToggleSabbathSchoolHighlightAction::class)->execute(
            new ToggleSabbathSchoolHighlightData($user, $content->id, 0, 16, '#FFEB3B'),
        );

        $this->assertFalse($result->created);
        $this->assertNull($result->highlight);
        $this->assertSoftDeleted('sabbath_school_highlights', [
            'user_id' => $user->id,
            'segment_content_id' => $content->id,
            'start_position' => 0,
            'end_position' => 16,
        ]);
    }

    public function test_toggling_off_then_on_recreates_the_highlight(): void
    {
        $user = User::factory()->create();
        $content = SabbathSchoolSegmentContent::factory()->create();
        $action = $this->app->make(ToggleSabbathSchoolHighlightAction::class);
        $data = new ToggleSabbathSchoolHighlightData($user, $content->id, 0, 16, '#FFEB3B');

        $action->execute($data);
        $action->execute($data);
        $action->execute($data);

        $this->assertSame(1, SabbathSchoolHighlight::query()
            ->where('user_id', $user->id)
            ->where('segment_content_id', $content->id)
            ->where('start_position', 0)
            ->where('end_position', 16)
            ->count());
        $this->assertSame(1, SabbathSchoolHighlight::query()
            ->onlyTrashed()
            ->where('user_id', $user->id)
            ->where('segment_content_id', $content->id)
            ->count());
    }

    public function test_different_ranges_on_the_same_content_are_independent(): void
    {
        $user = User::factory()->create();
        $content = SabbathSchoolSegmentContent::factory()->create();
        $action = $this->app->make(ToggleSabbathSchoolHighlightAction::class);

        $action->execute(new ToggleSabbathSchoolHighlightData($user, $content->id, 0, 4, '#FFEB3B'));
        $action->execute(new ToggleSabbathSchoolHighlightData($user, $content->id, 5, 10, '#FFEB3B'));

        $this->assertDatabaseCount('sabbath_school_highlights', 2);
    }
}

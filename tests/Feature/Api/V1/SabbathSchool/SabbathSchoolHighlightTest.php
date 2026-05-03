<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\SabbathSchool;

use App\Domain\SabbathSchool\Models\SabbathSchoolHighlight;
use App\Domain\SabbathSchool\Models\SabbathSchoolSegment;
use App\Domain\SabbathSchool\Models\SabbathSchoolSegmentContent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAuthentication;
use Tests\TestCase;

final class SabbathSchoolHighlightTest extends TestCase
{
    use InteractsWithAuthentication;
    use RefreshDatabase;

    public function test_toggle_creates_a_highlight_on_first_call(): void
    {
        $user = $this->givenAnAuthenticatedUser();
        $segment = SabbathSchoolSegment::factory()->create();
        $content = SabbathSchoolSegmentContent::factory()->forSegment($segment)->create();

        $this->postJson(route('sabbath-school.highlights.toggle'), [
            'segment_content_id' => $content->id,
            'start_position' => 0,
            'end_position' => 16,
            'color' => '#FFEB3B',
        ])
            ->assertCreated()
            ->assertJsonPath('data.segment_id', $segment->id)
            ->assertJsonPath('data.segment_content_id', $content->id)
            ->assertJsonPath('data.start_position', 0)
            ->assertJsonPath('data.end_position', 16)
            ->assertJsonPath('data.color', '#FFEB3B');

        $this->assertDatabaseHas('sabbath_school_highlights', [
            'user_id' => $user->id,
            'segment_content_id' => $content->id,
            'start_position' => 0,
            'end_position' => 16,
            'color' => '#FFEB3B',
        ]);
    }

    public function test_toggle_deletes_the_highlight_on_second_call(): void
    {
        $user = $this->givenAnAuthenticatedUser();
        $content = SabbathSchoolSegmentContent::factory()->create();
        SabbathSchoolHighlight::factory()
            ->forUser($user)
            ->forSegmentContent($content)
            ->create([
                'start_position' => 5,
                'end_position' => 12,
            ]);

        $this->postJson(route('sabbath-school.highlights.toggle'), [
            'segment_content_id' => $content->id,
            'start_position' => 5,
            'end_position' => 12,
            'color' => '#FFEB3B',
        ])
            ->assertOk()
            ->assertExactJson(['deleted' => true]);

        $this->assertSoftDeleted('sabbath_school_highlights', [
            'user_id' => $user->id,
            'segment_content_id' => $content->id,
            'start_position' => 5,
            'end_position' => 12,
        ]);
    }

    public function test_toggle_validates_required_fields(): void
    {
        $this->givenAnAuthenticatedUser();

        $this->postJson(route('sabbath-school.highlights.toggle'), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['segment_content_id', 'start_position', 'end_position', 'color']);
    }

    public function test_toggle_validates_unknown_segment_content_id(): void
    {
        $this->givenAnAuthenticatedUser();

        $this->postJson(route('sabbath-school.highlights.toggle'), [
            'segment_content_id' => 999_999,
            'start_position' => 0,
            'end_position' => 4,
            'color' => '#FFEB3B',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('segment_content_id');
    }

    public function test_toggle_rejects_invalid_color(): void
    {
        $this->givenAnAuthenticatedUser();
        $content = SabbathSchoolSegmentContent::factory()->create();

        $this->postJson(route('sabbath-school.highlights.toggle'), [
            'segment_content_id' => $content->id,
            'start_position' => 0,
            'end_position' => 4,
            'color' => 'not-a-color',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('color');
    }

    public function test_toggle_rejects_end_not_greater_than_start(): void
    {
        $this->givenAnAuthenticatedUser();
        $content = SabbathSchoolSegmentContent::factory()->create();

        $this->postJson(route('sabbath-school.highlights.toggle'), [
            'segment_content_id' => $content->id,
            'start_position' => 4,
            'end_position' => 4,
            'color' => '#FFEB3B',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('end_position');
    }

    public function test_list_returns_only_the_callers_highlights_for_the_segment(): void
    {
        $user = $this->givenAnAuthenticatedUser();
        $segment = SabbathSchoolSegment::factory()->create();
        $content = SabbathSchoolSegmentContent::factory()->forSegment($segment)->create();

        $other = SabbathSchoolSegment::factory()->create();
        $otherContent = SabbathSchoolSegmentContent::factory()->forSegment($other)->create();

        $otherUser = User::factory()->create();

        SabbathSchoolHighlight::factory()
            ->forUser($user)
            ->forSegmentContent($content)
            ->create();

        SabbathSchoolHighlight::factory()
            ->forUser($user)
            ->forSegmentContent($otherContent)
            ->create();

        SabbathSchoolHighlight::factory()
            ->forUser($otherUser)
            ->forSegmentContent($content)
            ->create();

        $this->getJson(route('sabbath-school.highlights.index', ['segment_id' => $segment->id]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.segment_content_id', $content->id);
    }

    public function test_list_filters_unmigrated_rows(): void
    {
        $user = $this->givenAnAuthenticatedUser();
        $segment = SabbathSchoolSegment::factory()->create();
        $content = SabbathSchoolSegmentContent::factory()->forSegment($segment)->create();

        // Migrated row.
        SabbathSchoolHighlight::factory()
            ->forUser($user)
            ->forSegmentContent($content)
            ->create();

        // Un-migrated row (no segment_content_id) — should not surface.
        SabbathSchoolHighlight::factory()
            ->forUser($user)
            ->create([
                'sabbath_school_segment_id' => $segment->id,
                'segment_content_id' => null,
                'start_position' => null,
                'end_position' => null,
                'color' => null,
                'passage' => 'GEN.1:1.VDC',
            ]);

        $this->getJson(route('sabbath-school.highlights.index', ['segment_id' => $segment->id]))
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_patch_updates_the_color(): void
    {
        $user = $this->givenAnAuthenticatedUser();
        $highlight = SabbathSchoolHighlight::factory()->forUser($user)->create([
            'color' => '#FFEB3B',
        ]);

        $this->patchJson(
            route('sabbath-school.highlights.update', ['highlight' => $highlight->id]),
            ['color' => '#00FF00'],
        )
            ->assertOk()
            ->assertJsonPath('data.color', '#00FF00');

        $this->assertSame('#00FF00', $highlight->refresh()->color);
    }

    public function test_patch_returns_404_for_cross_user_highlight(): void
    {
        $owner = User::factory()->create();
        $highlight = SabbathSchoolHighlight::factory()->forUser($owner)->create();

        $this->givenAnAuthenticatedUser();

        $this->patchJson(
            route('sabbath-school.highlights.update', ['highlight' => $highlight->id]),
            ['color' => '#00FF00'],
        )->assertNotFound();
    }

    public function test_list_requires_segment_id(): void
    {
        $this->givenAnAuthenticatedUser();

        $this->getJson(route('sabbath-school.highlights.index'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('segment_id');
    }

    public function test_highlight_endpoints_require_sanctum(): void
    {
        $content = SabbathSchoolSegmentContent::factory()->create();

        $this->postJson(route('sabbath-school.highlights.toggle'), [
            'segment_content_id' => $content->id,
            'start_position' => 0,
            'end_position' => 4,
            'color' => '#FFEB3B',
        ])->assertUnauthorized();

        $this->getJson(route('sabbath-school.highlights.index', ['segment_id' => $content->segment_id]))
            ->assertUnauthorized();
    }
}

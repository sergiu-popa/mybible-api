<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\SabbathSchool;

use App\Domain\SabbathSchool\Models\SabbathSchoolHighlight;
use App\Domain\SabbathSchool\Models\SabbathSchoolSegment;
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

        $this->postJson(route('sabbath-school.highlights.toggle'), [
            'segment_id' => $segment->id,
            'passage' => 'GEN.1:1.VDC',
        ])
            ->assertCreated()
            ->assertJsonPath('data.segment_id', $segment->id)
            ->assertJsonPath('data.passage', 'GEN.1:1.VDC');

        $this->assertDatabaseHas('sabbath_school_highlights', [
            'user_id' => $user->id,
            'sabbath_school_segment_id' => $segment->id,
            'passage' => 'GEN.1:1.VDC',
        ]);
    }

    public function test_toggle_deletes_the_highlight_on_second_call(): void
    {
        $user = $this->givenAnAuthenticatedUser();
        $segment = SabbathSchoolSegment::factory()->create();
        SabbathSchoolHighlight::factory()
            ->forUser($user)
            ->forSegment($segment)
            ->create(['passage' => 'GEN.1:1.VDC']);

        $this->postJson(route('sabbath-school.highlights.toggle'), [
            'segment_id' => $segment->id,
            'passage' => 'GEN.1:1.VDC',
        ])
            ->assertOk()
            ->assertExactJson(['deleted' => true]);

        $this->assertDatabaseMissing('sabbath_school_highlights', [
            'user_id' => $user->id,
            'sabbath_school_segment_id' => $segment->id,
            'passage' => 'GEN.1:1.VDC',
        ]);
    }

    public function test_toggle_rejects_unparseable_passage(): void
    {
        $this->givenAnAuthenticatedUser();
        $segment = SabbathSchoolSegment::factory()->create();

        $this->postJson(route('sabbath-school.highlights.toggle'), [
            'segment_id' => $segment->id,
            'passage' => 'not a reference',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('passage');

        $this->assertDatabaseCount('sabbath_school_highlights', 0);
    }

    public function test_toggle_validates_missing_segment_id(): void
    {
        $this->givenAnAuthenticatedUser();

        $this->postJson(route('sabbath-school.highlights.toggle'), [
            'passage' => 'GEN.1:1.VDC',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('segment_id');
    }

    public function test_toggle_validates_unknown_segment_id(): void
    {
        $this->givenAnAuthenticatedUser();

        $this->postJson(route('sabbath-school.highlights.toggle'), [
            'segment_id' => 999_999,
            'passage' => 'GEN.1:1.VDC',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('segment_id');
    }

    public function test_list_returns_only_the_callers_highlights_for_the_segment(): void
    {
        $user = $this->givenAnAuthenticatedUser();
        $segment = SabbathSchoolSegment::factory()->create();
        $otherSegment = SabbathSchoolSegment::factory()->create();
        $otherUser = User::factory()->create();

        SabbathSchoolHighlight::factory()
            ->forUser($user)
            ->forSegment($segment)
            ->create(['passage' => 'GEN.1:1.VDC']);

        SabbathSchoolHighlight::factory()
            ->forUser($user)
            ->forSegment($otherSegment)
            ->create(['passage' => 'GEN.2:1.VDC']);

        SabbathSchoolHighlight::factory()
            ->forUser($otherUser)
            ->forSegment($segment)
            ->create(['passage' => 'GEN.3:1.VDC']);

        $this->getJson(route('sabbath-school.highlights.index', ['segment_id' => $segment->id]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.passage', 'GEN.1:1.VDC');
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
        $segment = SabbathSchoolSegment::factory()->create();

        $this->postJson(route('sabbath-school.highlights.toggle'), [
            'segment_id' => $segment->id,
            'passage' => 'GEN.1:1.VDC',
        ])->assertUnauthorized();

        $this->getJson(route('sabbath-school.highlights.index', ['segment_id' => $segment->id]))
            ->assertUnauthorized();
    }
}

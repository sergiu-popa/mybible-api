<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Admin\SabbathSchool;

use App\Domain\SabbathSchool\Models\SabbathSchoolLesson;
use App\Domain\SabbathSchool\Models\SabbathSchoolSegment;
use App\Domain\SabbathSchool\Models\SabbathSchoolSegmentContent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ReorderTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): void
    {
        $admin = User::factory()->admin()->create();
        $token = $admin->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token);
    }

    public function test_segments_reorder_persists_full_ordering_inside_lesson(): void
    {
        $this->actingAsAdmin();

        $lesson = SabbathSchoolLesson::factory()->create();
        $a = SabbathSchoolSegment::factory()->create(['sabbath_school_lesson_id' => $lesson->id]);
        $b = SabbathSchoolSegment::factory()->create(['sabbath_school_lesson_id' => $lesson->id]);
        $c = SabbathSchoolSegment::factory()->create(['sabbath_school_lesson_id' => $lesson->id]);

        $this->postJson(
            route('admin.sabbath-school.lessons.segments.reorder', ['lesson' => $lesson->id]),
            ['ids' => [$c->id, $a->id, $b->id]],
        )->assertOk();

        $this->assertSame(1, $c->refresh()->position);
        $this->assertSame(2, $a->refresh()->position);
        $this->assertSame(3, $b->refresh()->position);
    }

    public function test_segments_reorder_returns_422_for_ids_from_other_lessons(): void
    {
        $this->actingAsAdmin();

        $lesson = SabbathSchoolLesson::factory()->create();
        $other = SabbathSchoolLesson::factory()->create();

        $a = SabbathSchoolSegment::factory()->create(['sabbath_school_lesson_id' => $lesson->id]);
        $foreigner = SabbathSchoolSegment::factory()->create([
            'sabbath_school_lesson_id' => $other->id,
            'position' => 99,
        ]);

        $this->postJson(
            route('admin.sabbath-school.lessons.segments.reorder', ['lesson' => $lesson->id]),
            ['ids' => [$a->id, $foreigner->id]],
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['ids']);

        $this->assertSame(99, $foreigner->refresh()->position);
    }

    public function test_segment_contents_reorder_returns_422_for_ids_from_other_segments(): void
    {
        $this->actingAsAdmin();

        $segment = SabbathSchoolSegment::factory()->create();
        $other = SabbathSchoolSegment::factory()->create();

        $insider = SabbathSchoolSegmentContent::factory()
            ->forSegment($segment)
            ->create();
        $foreigner = SabbathSchoolSegmentContent::factory()
            ->forSegment($other)
            ->atPosition(99)
            ->create();

        $this->postJson(
            route('admin.sabbath-school.segments.contents.reorder', ['segment' => $segment->id]),
            ['ids' => [$insider->id, $foreigner->id]],
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['ids']);

        $this->assertSame(99, $foreigner->refresh()->position);
    }

    public function test_segment_contents_reorder_persists_full_ordering_inside_segment(): void
    {
        $this->actingAsAdmin();

        $segment = SabbathSchoolSegment::factory()->create();
        $a = SabbathSchoolSegmentContent::factory()->forSegment($segment)->create();
        $b = SabbathSchoolSegmentContent::factory()->forSegment($segment)->create();

        $this->postJson(
            route('admin.sabbath-school.segments.contents.reorder', ['segment' => $segment->id]),
            ['ids' => [$b->id, $a->id]],
        )->assertOk();

        $this->assertSame(1, $b->refresh()->position);
        $this->assertSame(2, $a->refresh()->position);
    }

    public function test_segments_reorder_blocks_non_admin(): void
    {
        $lesson = SabbathSchoolLesson::factory()->create();
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson(
                route('admin.sabbath-school.lessons.segments.reorder', ['lesson' => $lesson->id]),
                ['ids' => [1]],
            )
            ->assertForbidden();
    }
}

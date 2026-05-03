<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Admin\SabbathSchool;

use App\Domain\SabbathSchool\Models\SabbathSchoolSegment;
use App\Domain\SabbathSchool\Models\SabbathSchoolSegmentContent;
use App\Domain\SabbathSchool\Support\SegmentContentType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SegmentContentCrudTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): void
    {
        $admin = User::factory()->admin()->create();
        $token = $admin->createToken('test')->plainTextToken;
        $this->withHeader('Authorization', 'Bearer ' . $token);
    }

    public function test_index_requires_admin(): void
    {
        $segment = SabbathSchoolSegment::factory()->create();
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson(route('admin.sabbath-school.segments.contents.index', ['segment' => $segment->id]))
            ->assertForbidden();
    }

    public function test_index_returns_segment_contents(): void
    {
        $this->actingAsAdmin();
        $segment = SabbathSchoolSegment::factory()->create();
        $a = SabbathSchoolSegmentContent::factory()->forSegment($segment)->atPosition(1)->create();
        $b = SabbathSchoolSegmentContent::factory()->forSegment($segment)->atPosition(2)->create();

        $this->getJson(route('admin.sabbath-school.segments.contents.index', ['segment' => $segment->id]))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $a->id)
            ->assertJsonPath('data.1.id', $b->id);
    }

    public function test_create_validates_payload(): void
    {
        $this->actingAsAdmin();
        $segment = SabbathSchoolSegment::factory()->create();

        $this->postJson(
            route('admin.sabbath-school.segments.contents.store', ['segment' => $segment->id]),
            ['type' => 'bogus'],
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['type', 'content']);
    }

    public function test_create_persists_typed_content(): void
    {
        $this->actingAsAdmin();
        $segment = SabbathSchoolSegment::factory()->create();

        $this->postJson(
            route('admin.sabbath-school.segments.contents.store', ['segment' => $segment->id]),
            [
                'type' => SegmentContentType::Question->value,
                'title' => 'Discussion question',
                'position' => 5,
                'content' => 'What does this teach us?',
            ],
        )
            ->assertCreated()
            ->assertJsonPath('data.type', 'question')
            ->assertJsonPath('data.position', 5);

        $this->assertDatabaseHas('sabbath_school_segment_contents', [
            'segment_id' => $segment->id,
            'type' => 'question',
            'title' => 'Discussion question',
            'position' => 5,
        ]);
    }

    public function test_update_persists_changes(): void
    {
        $this->actingAsAdmin();
        $content = SabbathSchoolSegmentContent::factory()->create(['title' => 'Old']);

        $this->patchJson(
            route('admin.sabbath-school.segment-contents.update', ['content' => $content->id]),
            ['title' => 'New'],
        )
            ->assertOk()
            ->assertJsonPath('data.title', 'New');

        $this->assertSame('New', $content->refresh()->title);
    }

    public function test_destroy_deletes_content(): void
    {
        $this->actingAsAdmin();
        $content = SabbathSchoolSegmentContent::factory()->create();

        $this->deleteJson(route('admin.sabbath-school.segment-contents.destroy', ['content' => $content->id]))
            ->assertNoContent();

        $this->assertDatabaseMissing('sabbath_school_segment_contents', ['id' => $content->id]);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\SabbathSchool\Actions;

use App\Domain\SabbathSchool\Actions\ToggleSabbathSchoolHighlightAction;
use App\Domain\SabbathSchool\DataTransferObjects\ToggleSabbathSchoolHighlightData;
use App\Domain\SabbathSchool\Exceptions\InvalidSabbathSchoolPassageException;
use App\Domain\SabbathSchool\Models\SabbathSchoolHighlight;
use App\Domain\SabbathSchool\Models\SabbathSchoolSegment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ToggleSabbathSchoolHighlightActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_inserts_a_highlight_when_none_exists(): void
    {
        $user = User::factory()->create();
        $segment = SabbathSchoolSegment::factory()->create();

        $result = $this->app->make(ToggleSabbathSchoolHighlightAction::class)->execute(
            new ToggleSabbathSchoolHighlightData($user, $segment->id, 'GEN.1:1.VDC'),
        );

        $this->assertTrue($result->created);
        $this->assertNotNull($result->highlight);
        $this->assertDatabaseHas('sabbath_school_highlights', [
            'user_id' => $user->id,
            'sabbath_school_segment_id' => $segment->id,
            'passage' => 'GEN.1:1.VDC',
        ]);
    }

    public function test_it_deletes_the_highlight_on_second_call(): void
    {
        $user = User::factory()->create();
        $segment = SabbathSchoolSegment::factory()->create();

        SabbathSchoolHighlight::factory()
            ->forUser($user)
            ->forSegment($segment)
            ->create(['passage' => 'GEN.1:1.VDC']);

        $result = $this->app->make(ToggleSabbathSchoolHighlightAction::class)->execute(
            new ToggleSabbathSchoolHighlightData($user, $segment->id, 'GEN.1:1.VDC'),
        );

        $this->assertFalse($result->created);
        $this->assertNull($result->highlight);
        $this->assertSoftDeleted('sabbath_school_highlights', [
            'user_id' => $user->id,
            'sabbath_school_segment_id' => $segment->id,
            'passage' => 'GEN.1:1.VDC',
        ]);
    }

    public function test_it_wraps_invalid_reference_as_domain_exception(): void
    {
        $user = User::factory()->create();
        $segment = SabbathSchoolSegment::factory()->create();

        $this->expectException(InvalidSabbathSchoolPassageException::class);

        try {
            $this->app->make(ToggleSabbathSchoolHighlightAction::class)->execute(
                new ToggleSabbathSchoolHighlightData($user, $segment->id, 'not a reference'),
            );
        } finally {
            $this->assertDatabaseCount('sabbath_school_highlights', 0);
        }
    }

    public function test_different_passages_on_the_same_segment_are_independent(): void
    {
        $user = User::factory()->create();
        $segment = SabbathSchoolSegment::factory()->create();

        $this->app->make(ToggleSabbathSchoolHighlightAction::class)->execute(
            new ToggleSabbathSchoolHighlightData($user, $segment->id, 'GEN.1:1.VDC'),
        );

        $this->app->make(ToggleSabbathSchoolHighlightAction::class)->execute(
            new ToggleSabbathSchoolHighlightData($user, $segment->id, 'GEN.1:2.VDC'),
        );

        $this->assertDatabaseCount('sabbath_school_highlights', 2);
    }
}

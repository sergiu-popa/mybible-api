<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\SabbathSchool\Actions;

use App\Domain\SabbathSchool\Actions\PatchSabbathSchoolHighlightColorAction;
use App\Domain\SabbathSchool\Models\SabbathSchoolHighlight;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PatchSabbathSchoolHighlightColorActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_updates_only_the_color(): void
    {
        $highlight = SabbathSchoolHighlight::factory()->create([
            'start_position' => 4,
            'end_position' => 8,
            'color' => '#FFEB3B',
        ]);

        $updated = $this->app->make(PatchSabbathSchoolHighlightColorAction::class)
            ->execute($highlight, '#00FF00');

        $this->assertSame('#00FF00', $updated->color);
        $this->assertSame(4, $updated->start_position);
        $this->assertSame(8, $updated->end_position);
        $this->assertDatabaseHas('sabbath_school_highlights', [
            'id' => $highlight->id,
            'color' => '#00FF00',
            'start_position' => 4,
            'end_position' => 8,
        ]);
    }
}

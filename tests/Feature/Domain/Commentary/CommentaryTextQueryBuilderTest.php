<?php

declare(strict_types=1);

namespace Tests\Feature\Domain\Commentary;

use App\Domain\Commentary\Models\Commentary;
use App\Domain\Commentary\Models\CommentaryText;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CommentaryTextQueryBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_covering_verse_matches_a_single_verse_block(): void
    {
        $commentary = Commentary::factory()->create();

        $hit = CommentaryText::factory()->create([
            'commentary_id' => $commentary->id,
            'book' => 'GEN',
            'chapter' => 1,
            'position' => 5,
            'verse_from' => 5,
            'verse_to' => 5,
            'verse_label' => '5',
        ]);

        $matches = CommentaryText::query()
            ->coveringVerse('GEN', 1, 5)
            ->pluck('id')
            ->all();

        $this->assertSame([$hit->id], $matches);
    }

    public function test_covering_verse_matches_a_multi_verse_block(): void
    {
        $commentary = Commentary::factory()->create();

        $block = CommentaryText::factory()->forVerseRange(1, 7)->create([
            'commentary_id' => $commentary->id,
            'book' => 'GEN',
            'chapter' => 1,
            'position' => 1,
        ]);

        foreach ([1, 4, 7] as $verse) {
            $matches = CommentaryText::query()
                ->coveringVerse('GEN', 1, $verse)
                ->pluck('id')
                ->all();

            $this->assertSame([$block->id], $matches, "verse {$verse} should hit");
        }
    }

    public function test_covering_verse_handles_open_ended_blocks(): void
    {
        $commentary = Commentary::factory()->create();

        $block = CommentaryText::factory()->openEnded(10)->create([
            'commentary_id' => $commentary->id,
            'book' => 'GEN',
            'chapter' => 1,
            'position' => 10,
        ]);

        $matches = CommentaryText::query()
            ->coveringVerse('GEN', 1, 99)
            ->pluck('id')
            ->all();

        $this->assertSame([$block->id], $matches);

        $miss = CommentaryText::query()
            ->coveringVerse('GEN', 1, 5)
            ->pluck('id')
            ->all();

        $this->assertSame([], $miss);
    }

    public function test_covering_verse_misses_blocks_outside_the_range(): void
    {
        $commentary = Commentary::factory()->create();

        CommentaryText::factory()->forVerseRange(8, 10)->create([
            'commentary_id' => $commentary->id,
            'book' => 'GEN',
            'chapter' => 1,
            'position' => 8,
        ]);

        $matches = CommentaryText::query()
            ->coveringVerse('GEN', 1, 1)
            ->pluck('id')
            ->all();

        $this->assertSame([], $matches);
    }

    public function test_covering_verse_scopes_to_book_and_chapter(): void
    {
        $commentary = Commentary::factory()->create();

        CommentaryText::factory()->forVerseRange(1, 5)->create([
            'commentary_id' => $commentary->id,
            'book' => 'EXO',
            'chapter' => 1,
            'position' => 1,
        ]);

        $matches = CommentaryText::query()
            ->coveringVerse('GEN', 1, 1)
            ->pluck('id')
            ->all();

        $this->assertSame([], $matches);
    }
}

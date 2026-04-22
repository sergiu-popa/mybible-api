<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Bible\Models;

use App\Domain\Bible\Models\BibleBook;
use App\Domain\Bible\Models\BibleVerse;
use App\Domain\Bible\Models\BibleVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class BibleVerseTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_verses_ordered_by_chapter_and_verse(): void
    {
        $version = BibleVersion::factory()->create();
        $book = BibleBook::factory()->create();

        BibleVerse::factory()->create([
            'bible_version_id' => $version->id,
            'bible_book_id' => $book->id,
            'chapter' => 2,
            'verse' => 1,
        ]);
        BibleVerse::factory()->create([
            'bible_version_id' => $version->id,
            'bible_book_id' => $book->id,
            'chapter' => 1,
            'verse' => 2,
        ]);
        BibleVerse::factory()->create([
            'bible_version_id' => $version->id,
            'bible_book_id' => $book->id,
            'chapter' => 1,
            'verse' => 1,
        ]);

        $ordered = BibleVerse::query()
            ->where('bible_version_id', $version->id)
            ->where('bible_book_id', $book->id)
            ->orderBy('chapter')
            ->orderBy('verse')
            ->get(['chapter', 'verse'])
            ->map(fn (BibleVerse $v): array => [$v->chapter, $v->verse])
            ->all();

        $this->assertSame([[1, 1], [1, 2], [2, 1]], $ordered);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Bible\Models;

use App\Domain\Bible\Models\BibleBook;
use App\Domain\Bible\Models\BibleChapter;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class BibleChapterTest extends TestCase
{
    use RefreshDatabase;

    public function test_verse_count_casts_to_int(): void
    {
        $book = BibleBook::factory()->create();
        $chapter = BibleChapter::factory()->create([
            'bible_book_id' => $book->id,
            'number' => 1,
            'verse_count' => '42',
        ]);

        $this->assertSame(42, $chapter->fresh()->verse_count);
    }

    public function test_bible_book_id_and_number_are_unique(): void
    {
        $book = BibleBook::factory()->create();
        BibleChapter::factory()->create([
            'bible_book_id' => $book->id,
            'number' => 1,
        ]);

        $this->expectException(QueryException::class);

        BibleChapter::factory()->create([
            'bible_book_id' => $book->id,
            'number' => 1,
        ]);
    }
}

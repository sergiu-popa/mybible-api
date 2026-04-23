<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Hymnal\QueryBuilders;

use App\Domain\Hymnal\Models\HymnalBook;
use App\Domain\Hymnal\Models\HymnalSong;
use App\Domain\Shared\Enums\Language;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class HymnalSongQueryBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_for_book_scopes_to_the_given_book(): void
    {
        $first = HymnalBook::factory()->create();
        $second = HymnalBook::factory()->create();
        $ownSong = HymnalSong::factory()->create(['hymnal_book_id' => $first->id]);
        HymnalSong::factory()->create(['hymnal_book_id' => $second->id]);

        $ids = HymnalSong::query()->forBook($first)->pluck('id')->all();

        $this->assertSame([$ownSong->id], $ids);
    }

    public function test_search_matches_on_number_when_query_is_numeric(): void
    {
        $book = HymnalBook::factory()->create();
        $match = HymnalSong::factory()->create([
            'hymnal_book_id' => $book->id,
            'number' => 77,
            'title' => ['en' => 'Different Title'],
        ]);
        HymnalSong::factory()->create([
            'hymnal_book_id' => $book->id,
            'number' => 88,
            'title' => ['en' => 'Other Title'],
        ]);

        $ids = HymnalSong::query()
            ->forBook($book)
            ->search('77', Language::En)
            ->pluck('id')
            ->all();

        $this->assertSame([$match->id], $ids);
    }

    public function test_search_matches_localised_title(): void
    {
        $book = HymnalBook::factory()->create();
        $match = HymnalSong::factory()->create([
            'hymnal_book_id' => $book->id,
            'number' => 1,
            'title' => ['en' => 'Amazing Grace', 'ro' => 'Har Minunat'],
        ]);
        HymnalSong::factory()->create([
            'hymnal_book_id' => $book->id,
            'number' => 2,
            'title' => ['en' => 'Great is Thy Faithfulness', 'ro' => 'Credincios Esti'],
        ]);

        $ids = HymnalSong::query()
            ->forBook($book)
            ->search('Har', Language::Ro)
            ->pluck('id')
            ->all();

        $this->assertSame([$match->id], $ids);
    }

    public function test_search_does_not_match_other_language_keys(): void
    {
        $book = HymnalBook::factory()->create();
        HymnalSong::factory()->create([
            'hymnal_book_id' => $book->id,
            'number' => 1,
            'title' => ['en' => 'Amazing Grace', 'ro' => 'Har Minunat'],
        ]);

        $ids = HymnalSong::query()
            ->forBook($book)
            ->search('Amazing', Language::Ro)
            ->pluck('id')
            ->all();

        $this->assertSame([], $ids);
    }

    public function test_search_with_empty_query_returns_all_rows(): void
    {
        $book = HymnalBook::factory()->create();
        HymnalSong::factory()->count(3)->create(['hymnal_book_id' => $book->id]);

        $count = HymnalSong::query()
            ->forBook($book)
            ->search(null, Language::En)
            ->count();

        $this->assertSame(3, $count);
    }
}

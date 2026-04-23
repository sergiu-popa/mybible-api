<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Hymnal;

use App\Domain\Hymnal\Models\HymnalBook;
use App\Domain\Hymnal\Models\HymnalSong;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithApiKeyClient;
use Tests\TestCase;

final class ListHymnalBookSongsTest extends TestCase
{
    use RefreshDatabase;
    use WithApiKeyClient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpApiKeyClient();
    }

    public function test_it_returns_only_songs_for_the_given_book(): void
    {
        $book = HymnalBook::factory()->create();
        $otherBook = HymnalBook::factory()->create();

        HymnalSong::factory()->count(2)->create(['hymnal_book_id' => $book->id]);
        HymnalSong::factory()->create(['hymnal_book_id' => $otherBook->id]);

        $this->withHeaders($this->apiKeyHeaders())
            ->getJson(route('hymnal-books.songs', ['book' => $book->slug]))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure([
                'data' => [[
                    'id',
                    'number',
                    'title',
                    'book' => ['id', 'slug'],
                ]],
            ]);
    }

    public function test_it_orders_by_song_number(): void
    {
        $book = HymnalBook::factory()->create();
        $second = HymnalSong::factory()->create(['hymnal_book_id' => $book->id, 'number' => 20]);
        $first = HymnalSong::factory()->create(['hymnal_book_id' => $book->id, 'number' => 10]);

        $this->withHeaders($this->apiKeyHeaders())
            ->getJson(route('hymnal-books.songs', ['book' => $book->slug]))
            ->assertOk()
            ->assertJsonPath('data.0.id', $first->id)
            ->assertJsonPath('data.1.id', $second->id);
    }

    public function test_it_matches_numeric_search_on_song_number(): void
    {
        $book = HymnalBook::factory()->create();
        $match = HymnalSong::factory()->create([
            'hymnal_book_id' => $book->id,
            'number' => 42,
            'title' => ['en' => 'Great Is Thy Faithfulness'],
        ]);
        HymnalSong::factory()->create([
            'hymnal_book_id' => $book->id,
            'number' => 99,
            'title' => ['en' => 'Amazing Grace'],
        ]);

        $this->withHeaders($this->apiKeyHeaders())
            ->getJson(route('hymnal-books.songs', ['book' => $book->slug, 'search' => '42']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $match->id);
    }

    public function test_it_matches_textual_search_on_title(): void
    {
        $book = HymnalBook::factory()->create();
        $match = HymnalSong::factory()->create([
            'hymnal_book_id' => $book->id,
            'number' => 1,
            'title' => ['en' => 'Amazing Grace'],
        ]);
        HymnalSong::factory()->create([
            'hymnal_book_id' => $book->id,
            'number' => 2,
            'title' => ['en' => 'How Great Thou Art'],
        ]);

        $this->withHeaders($this->apiKeyHeaders())
            ->getJson(route('hymnal-books.songs', ['book' => $book->slug, 'search' => 'Amazing']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $match->id);
    }

    public function test_it_returns_404_for_unknown_book(): void
    {
        $this->withHeaders($this->apiKeyHeaders())
            ->getJson(route('hymnal-books.songs', ['book' => 'missing-book']))
            ->assertNotFound();
    }

    public function test_it_rejects_per_page_above_the_cap(): void
    {
        $book = HymnalBook::factory()->create();

        $this->withHeaders($this->apiKeyHeaders())
            ->getJson(route('hymnal-books.songs', ['book' => $book->slug, 'per_page' => 201]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['per_page']);
    }

    public function test_it_rejects_missing_credentials(): void
    {
        $book = HymnalBook::factory()->create();

        $this->getJson(route('hymnal-books.songs', ['book' => $book->slug]))
            ->assertUnauthorized();
    }
}

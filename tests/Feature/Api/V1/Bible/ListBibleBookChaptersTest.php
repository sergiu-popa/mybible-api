<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Bible;

use App\Domain\Bible\Models\BibleBook;
use App\Domain\Bible\Models\BibleChapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithApiKeyClient;
use Tests\TestCase;

final class ListBibleBookChaptersTest extends TestCase
{
    use RefreshDatabase;
    use WithApiKeyClient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpApiKeyClient();
    }

    public function test_it_resolves_the_book_by_abbreviation(): void
    {
        $book = BibleBook::factory()->genesis()->create();
        foreach ([1, 2, 3] as $number) {
            BibleChapter::factory()->create([
                'bible_book_id' => $book->id,
                'number' => $number,
                'verse_count' => 10 * $number,
            ]);
        }

        $response = $this
            ->withHeaders($this->apiKeyHeaders())
            ->getJson(route('books.chapters', ['book' => 'GEN']));

        $response
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure([
                'data' => [['number', 'verse_count']],
            ])
            ->assertJsonPath('data.0.number', 1)
            ->assertJsonPath('data.0.verse_count', 10)
            ->assertJsonPath('data.2.number', 3)
            ->assertJsonPath('data.2.verse_count', 30);
    }

    public function test_it_returns_404_for_an_unknown_abbreviation(): void
    {
        $this
            ->withHeaders($this->apiKeyHeaders())
            ->getJson(route('books.chapters', ['book' => 'XYZ']))
            ->assertNotFound();
    }

    public function test_it_rejects_missing_api_key(): void
    {
        BibleBook::factory()->genesis()->create();

        $this->getJson(route('books.chapters', ['book' => 'GEN']))
            ->assertUnauthorized();
    }
}

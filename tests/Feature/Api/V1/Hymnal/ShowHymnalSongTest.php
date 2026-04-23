<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Hymnal;

use App\Domain\Hymnal\Models\HymnalBook;
use App\Domain\Hymnal\Models\HymnalSong;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithApiKeyClient;
use Tests\TestCase;

final class ShowHymnalSongTest extends TestCase
{
    use RefreshDatabase;
    use WithApiKeyClient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpApiKeyClient();
    }

    public function test_it_returns_full_song_payload(): void
    {
        $book = HymnalBook::factory()->create([
            'name' => ['en' => 'Songs of Praise'],
        ]);
        $song = HymnalSong::factory()->create([
            'hymnal_book_id' => $book->id,
            'number' => 5,
            'title' => ['en' => 'Holy Holy Holy'],
            'author' => ['en' => 'Reginald Heber'],
            'composer' => ['en' => 'John B. Dykes'],
            'copyright' => ['en' => 'Public Domain'],
            'stanzas' => [
                'en' => [
                    ['index' => 1, 'text' => 'Holy, holy, holy', 'is_chorus' => false],
                    ['index' => 2, 'text' => 'Chorus line', 'is_chorus' => true],
                ],
            ],
        ]);

        $response = $this->withHeaders($this->apiKeyHeaders())
            ->getJson(route('hymnal-songs.show', ['song' => $song->id]));

        $response
            ->assertOk()
            ->assertJsonPath('data.id', $song->id)
            ->assertJsonPath('data.number', 5)
            ->assertJsonPath('data.title', 'Holy Holy Holy')
            ->assertJsonPath('data.author', 'Reginald Heber')
            ->assertJsonPath('data.composer', 'John B. Dykes')
            ->assertJsonPath('data.copyright', 'Public Domain')
            ->assertJsonPath('data.stanzas.0.index', 1)
            ->assertJsonPath('data.stanzas.0.text', 'Holy, holy, holy')
            ->assertJsonPath('data.stanzas.0.is_chorus', false)
            ->assertJsonPath('data.stanzas.1.is_chorus', true)
            ->assertJsonPath('data.book.id', $book->id)
            ->assertJsonPath('data.book.slug', $book->slug)
            ->assertJsonPath('data.book.name', 'Songs of Praise');
    }

    public function test_it_falls_back_to_english_for_a_missing_language_key(): void
    {
        $book = HymnalBook::factory()->create();
        $song = HymnalSong::factory()->create([
            'hymnal_book_id' => $book->id,
            'title' => ['en' => 'Only English'],
            'stanzas' => [
                'en' => [['index' => 1, 'text' => 'English stanza', 'is_chorus' => false]],
            ],
        ]);

        $this->withHeaders($this->apiKeyHeaders())
            ->getJson(route('hymnal-songs.show', ['song' => $song->id, 'language' => 'hu']))
            ->assertOk()
            ->assertJsonPath('data.title', 'Only English')
            ->assertJsonPath('data.stanzas.0.text', 'English stanza');
    }

    public function test_it_returns_404_for_unknown_song(): void
    {
        $this->withHeaders($this->apiKeyHeaders())
            ->getJson(route('hymnal-songs.show', ['song' => 9999]))
            ->assertNotFound();
    }

    public function test_it_sets_public_cache_headers(): void
    {
        $song = HymnalSong::factory()->create();

        $response = $this->withHeaders($this->apiKeyHeaders())
            ->getJson(route('hymnal-songs.show', ['song' => $song->id]));

        $response->assertOk();
        $cacheControl = (string) $response->headers->get('Cache-Control');
        $this->assertStringContainsString('public', $cacheControl);
        $this->assertStringContainsString('max-age=3600', $cacheControl);
    }

    public function test_it_rejects_missing_credentials(): void
    {
        $song = HymnalSong::factory()->create();

        $this->getJson(route('hymnal-songs.show', ['song' => $song->id]))
            ->assertUnauthorized();
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Hymnal;

use App\Domain\Hymnal\Models\HymnalBook;
use App\Domain\Hymnal\Models\HymnalSong;
use App\Domain\Shared\Enums\Language;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAuthentication;
use Tests\Concerns\WithApiKeyClient;
use Tests\TestCase;

final class ListHymnalBooksTest extends TestCase
{
    use InteractsWithAuthentication;
    use RefreshDatabase;
    use WithApiKeyClient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpApiKeyClient();
    }

    public function test_it_returns_paginated_books(): void
    {
        HymnalBook::factory()->count(3)->create();

        $response = $this->withHeaders($this->apiKeyHeaders())
            ->getJson(route('hymnal-books.index'));

        $response
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure([
                'data' => [[
                    'id',
                    'slug',
                    'name',
                    'language',
                    'song_count',
                ]],
                'meta' => ['per_page', 'current_page', 'total'],
                'links',
            ]);
    }

    public function test_it_filters_by_language(): void
    {
        $english = HymnalBook::factory()->forLanguage(Language::En)->create();
        HymnalBook::factory()->forLanguage(Language::Ro)->create();

        $this->withHeaders($this->apiKeyHeaders())
            ->getJson(route('hymnal-books.index', ['language' => 'en']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $english->id);
    }

    public function test_it_includes_song_count(): void
    {
        $book = HymnalBook::factory()->create();
        HymnalSong::factory()->count(4)->create(['hymnal_book_id' => $book->id]);

        $this->withHeaders($this->apiKeyHeaders())
            ->getJson(route('hymnal-books.index'))
            ->assertOk()
            ->assertJsonPath('data.0.song_count', 4);
    }

    public function test_it_sets_public_cache_headers(): void
    {
        HymnalBook::factory()->create();

        $response = $this->withHeaders($this->apiKeyHeaders())
            ->getJson(route('hymnal-books.index'));

        $response->assertOk();
        $cacheControl = (string) $response->headers->get('Cache-Control');
        $this->assertStringContainsString('public', $cacheControl);
        $this->assertStringContainsString('max-age=3600', $cacheControl);
    }

    public function test_it_resolves_name_for_the_requested_language(): void
    {
        HymnalBook::factory()->forLanguage(Language::Ro)->create([
            'name' => ['en' => 'Songs of Praise', 'ro' => 'Cantari de Laudă'],
        ]);

        $this->withHeaders($this->apiKeyHeaders())
            ->getJson(route('hymnal-books.index', ['language' => 'ro']))
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Cantari de Laudă');
    }

    public function test_it_accepts_sanctum_auth(): void
    {
        HymnalBook::factory()->create();

        $this->givenAnAuthenticatedUser();

        $this->getJson(route('hymnal-books.index'))
            ->assertOk();
    }

    public function test_it_rejects_missing_credentials(): void
    {
        $this->getJson(route('hymnal-books.index'))
            ->assertUnauthorized();
    }

    public function test_it_validates_the_language_filter(): void
    {
        $this->withHeaders($this->apiKeyHeaders())
            ->getJson(route('hymnal-books.index', ['language' => 'zz']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['language']);
    }
}

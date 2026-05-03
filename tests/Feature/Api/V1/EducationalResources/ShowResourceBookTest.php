<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\EducationalResources;

use App\Domain\EducationalResources\Models\ResourceBook;
use App\Domain\EducationalResources\Models\ResourceBookChapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithApiKeyClient;
use Tests\TestCase;

final class ShowResourceBookTest extends TestCase
{
    use RefreshDatabase;
    use WithApiKeyClient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpApiKeyClient();
    }

    public function test_it_returns_book_with_chapters(): void
    {
        $book = ResourceBook::factory()->published()->create();
        $chapter1 = ResourceBookChapter::factory()->forBook($book)->create([
            'position' => 1,
            'title' => 'Intro',
            'audio_cdn_url' => 'https://cdn.example.com/a.mp3',
        ]);
        $chapter2 = ResourceBookChapter::factory()->forBook($book)->create([
            'position' => 2,
            'title' => 'Body',
            'audio_cdn_url' => null,
            'audio_embed' => null,
        ]);

        $this->withHeaders($this->apiKeyHeaders())
            ->getJson(route('resource-books.show', ['book' => $book->slug]))
            ->assertOk()
            ->assertJsonPath('data.slug', $book->slug)
            ->assertJsonPath('data.chapters.0.id', $chapter1->id)
            ->assertJsonPath('data.chapters.0.has_audio', true)
            ->assertJsonPath('data.chapters.1.id', $chapter2->id)
            ->assertJsonPath('data.chapters.1.has_audio', false);
    }

    public function test_it_returns_404_for_a_draft(): void
    {
        $book = ResourceBook::factory()->draft()->create();

        $this->withHeaders($this->apiKeyHeaders())
            ->getJson(route('resource-books.show', ['book' => $book->slug]))
            ->assertNotFound();
    }

    public function test_it_returns_404_for_unknown_slug(): void
    {
        $this->withHeaders($this->apiKeyHeaders())
            ->getJson(route('resource-books.show', ['book' => 'no-such-book']))
            ->assertNotFound();
    }

    public function test_it_sets_public_cache_headers(): void
    {
        $book = ResourceBook::factory()->published()->create();
        ResourceBookChapter::factory()->forBook($book)->create(['position' => 1]);

        $response = $this->withHeaders($this->apiKeyHeaders())
            ->getJson(route('resource-books.show', ['book' => $book->slug]));

        $response->assertOk();
        $cacheControl = (string) $response->headers->get('Cache-Control');
        $this->assertStringContainsString('public', $cacheControl);
        $this->assertStringContainsString('max-age=3600', $cacheControl);
    }
}

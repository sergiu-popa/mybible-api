<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\EducationalResources;

use App\Domain\EducationalResources\Models\ResourceBook;
use App\Domain\Shared\Enums\Language;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithApiKeyClient;
use Tests\TestCase;

final class ListResourceBooksTest extends TestCase
{
    use RefreshDatabase;
    use WithApiKeyClient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpApiKeyClient();
    }

    public function test_it_lists_published_resource_books(): void
    {
        $book = ResourceBook::factory()
            ->published()
            ->forLanguage(Language::Ro)
            ->create(['name' => 'A Book']);

        $this->withHeaders($this->apiKeyHeaders())
            ->getJson(route('resource-books.index', ['language' => 'ro']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', $book->slug)
            ->assertJsonPath('data.0.name', 'A Book')
            ->assertJsonStructure([
                'data' => [['slug', 'name', 'language', 'description', 'cover_image_url', 'author', 'published_at', 'chapter_count']],
            ]);
    }

    public function test_it_hides_drafts_from_public_listing(): void
    {
        ResourceBook::factory()->draft()->forLanguage(Language::Ro)->create();
        ResourceBook::factory()->published()->forLanguage(Language::Ro)->create();

        $this->withHeaders($this->apiKeyHeaders())
            ->getJson(route('resource-books.index', ['language' => 'ro']))
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_it_scopes_to_the_requested_language(): void
    {
        $expected = ResourceBook::factory()->published()->forLanguage(Language::En)->create();
        ResourceBook::factory()->published()->forLanguage(Language::Ro)->create();

        $this->withHeaders($this->apiKeyHeaders())
            ->getJson(route('resource-books.index', ['language' => 'en']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', $expected->slug);
    }

    public function test_it_sets_public_cache_headers(): void
    {
        ResourceBook::factory()->published()->create();

        $response = $this->withHeaders($this->apiKeyHeaders())
            ->getJson(route('resource-books.index'));

        $response->assertOk();
        $cacheControl = (string) $response->headers->get('Cache-Control');
        $this->assertStringContainsString('public', $cacheControl);
        $this->assertStringContainsString('max-age=3600', $cacheControl);
    }

    public function test_it_rejects_missing_credentials(): void
    {
        $this->getJson(route('resource-books.index'))->assertUnauthorized();
    }

    public function test_it_validates_the_language_filter(): void
    {
        $this->withHeaders($this->apiKeyHeaders())
            ->getJson(route('resource-books.index', ['language' => 'zz']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['language']);
    }

    public function test_it_returns_pagination_meta(): void
    {
        ResourceBook::factory()->published()->count(2)->create();

        $this->withHeaders($this->apiKeyHeaders())
            ->getJson(route('resource-books.index'))
            ->assertOk()
            ->assertJsonStructure(['data', 'links', 'meta']);
    }
}

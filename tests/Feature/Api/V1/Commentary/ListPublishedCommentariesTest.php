<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Commentary;

use App\Domain\Commentary\Models\Commentary;
use App\Domain\Shared\Enums\Language;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithApiKeyClient;
use Tests\TestCase;

final class ListPublishedCommentariesTest extends TestCase
{
    use RefreshDatabase;
    use WithApiKeyClient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpApiKeyClient();
    }

    public function test_it_lists_published_commentaries_for_the_request_language(): void
    {
        $expected = Commentary::factory()
            ->published()
            ->forLanguage(Language::Ro)
            ->create([
                'abbreviation' => 'SDA',
                'name' => ['ro' => 'Comentariu Biblic SDA', 'en' => 'SDA Bible Commentary'],
            ]);

        $this->withHeaders($this->apiKeyHeaders())
            ->getJson(route('commentaries.index', ['language' => 'ro']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonStructure([
                'data' => [[
                    'slug',
                    'name',
                    'abbreviation',
                    'language',
                ]],
            ])
            ->assertJsonPath('data.0.slug', $expected->slug)
            ->assertJsonPath('data.0.name', 'Comentariu Biblic SDA');
    }

    public function test_it_hides_drafts_from_public_listing(): void
    {
        Commentary::factory()->draft()->forLanguage(Language::Ro)->create();
        Commentary::factory()->published()->forLanguage(Language::Ro)->create();

        $this->withHeaders($this->apiKeyHeaders())
            ->getJson(route('commentaries.index', ['language' => 'ro']))
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_it_scopes_to_the_requested_language(): void
    {
        Commentary::factory()->published()->forLanguage(Language::En)->create();
        Commentary::factory()->published()->forLanguage(Language::Ro)->create();

        $this->withHeaders($this->apiKeyHeaders())
            ->getJson(route('commentaries.index', ['language' => 'en']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.language', 'en');
    }

    public function test_it_sets_public_cache_headers(): void
    {
        Commentary::factory()->published()->create();

        $response = $this->withHeaders($this->apiKeyHeaders())
            ->getJson(route('commentaries.index'));

        $response->assertOk();
        $cacheControl = (string) $response->headers->get('Cache-Control');
        $this->assertStringContainsString('public', $cacheControl);
        $this->assertStringContainsString('max-age=3600', $cacheControl);
    }

    public function test_it_rejects_missing_credentials(): void
    {
        $this->getJson(route('commentaries.index'))
            ->assertUnauthorized();
    }

    public function test_it_validates_the_language_filter(): void
    {
        $this->withHeaders($this->apiKeyHeaders())
            ->getJson(route('commentaries.index', ['language' => 'fr']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['language']);
    }
}

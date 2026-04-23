<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\News;

use App\Domain\News\Models\News;
use App\Domain\Shared\Enums\Language;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAuthentication;
use Tests\Concerns\WithApiKeyClient;
use Tests\TestCase;

final class ListNewsTest extends TestCase
{
    use InteractsWithAuthentication;
    use RefreshDatabase;
    use WithApiKeyClient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpApiKeyClient();
    }

    public function test_it_returns_paginated_news_newest_first(): void
    {
        News::factory()->forLanguage(Language::En)->publishedAt(CarbonImmutable::create(2026, 1, 1))->create([
            'title' => 'Older',
        ]);
        News::factory()->forLanguage(Language::En)->publishedAt(CarbonImmutable::create(2026, 4, 20))->create([
            'title' => 'Newer',
        ]);

        $response = $this->withHeaders($this->apiKeyHeaders())
            ->getJson(route('news.index'));

        $response
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure([
                'data' => [[
                    'id',
                    'title',
                    'summary',
                    'content',
                    'published_at',
                    'image_url',
                    'language',
                ]],
                'meta' => ['per_page', 'current_page', 'total'],
                'links',
            ])
            ->assertJsonPath('data.0.title', 'Newer')
            ->assertJsonPath('data.1.title', 'Older');
    }

    public function test_it_filters_by_explicit_language_parameter(): void
    {
        $ro = News::factory()->forLanguage(Language::Ro)->create(['title' => 'RO news']);
        News::factory()->forLanguage(Language::En)->create(['title' => 'EN news']);

        $this->withHeaders($this->apiKeyHeaders())
            ->getJson(route('news.index', ['language' => 'ro']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $ro->id);
    }

    public function test_it_filters_by_language_resolved_from_middleware(): void
    {
        // The ResolveRequestLanguage middleware falls back to `en` when no
        // `?language=` is provided, so the en row should surface.
        News::factory()->forLanguage(Language::En)->create(['title' => 'EN news']);
        News::factory()->forLanguage(Language::Ro)->create(['title' => 'RO news']);

        $this->withHeaders($this->apiKeyHeaders())
            ->getJson(route('news.index'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'EN news');
    }

    public function test_it_excludes_unpublished_and_future_rows(): void
    {
        News::factory()->forLanguage(Language::En)->publishedAt(CarbonImmutable::now()->subHour())->create([
            'title' => 'visible',
        ]);
        News::factory()->forLanguage(Language::En)->unpublished()->create(['title' => 'draft']);
        News::factory()->forLanguage(Language::En)->scheduledFor(CarbonImmutable::now()->addDay())->create([
            'title' => 'future',
        ]);

        $this->withHeaders($this->apiKeyHeaders())
            ->getJson(route('news.index'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'visible');
    }

    public function test_it_honours_per_page(): void
    {
        News::factory()->count(6)->forLanguage(Language::En)->create();

        $response = $this->withHeaders($this->apiKeyHeaders())
            ->getJson(route('news.index', ['per_page' => 2]));

        $response
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.total', 6);
    }

    public function test_it_rejects_per_page_above_the_max(): void
    {
        $this->withHeaders($this->apiKeyHeaders())
            ->getJson(route('news.index', ['per_page' => 9999]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('per_page');
    }

    public function test_it_sets_public_cache_headers(): void
    {
        News::factory()->forLanguage(Language::En)->create();

        $response = $this->withHeaders($this->apiKeyHeaders())
            ->getJson(route('news.index'));

        $response->assertOk();
        $cacheControl = (string) $response->headers->get('Cache-Control');
        $this->assertStringContainsString('public', $cacheControl);
        $this->assertStringContainsString('max-age=300', $cacheControl);
    }

    public function test_it_accepts_sanctum_auth(): void
    {
        News::factory()->forLanguage(Language::En)->create();

        $this->givenAnAuthenticatedUser();

        $this->getJson(route('news.index'))->assertOk();
    }

    public function test_it_rejects_missing_credentials(): void
    {
        $this->getJson(route('news.index'))->assertUnauthorized();
    }
}

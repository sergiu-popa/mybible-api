<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\News;

use App\Domain\News\Models\News;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithApiKeyClient;
use Tests\TestCase;

final class ShowNewsTest extends TestCase
{
    use RefreshDatabase;
    use WithApiKeyClient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpApiKeyClient();
    }

    public function test_it_returns_published_news_detail(): void
    {
        $news = News::factory()->create([
            'language' => 'ro',
            'title' => 'Title',
            'summary' => 'Summary',
            'content' => 'Full body content.',
            'published_at' => CarbonImmutable::now()->subHour(),
        ]);

        $this->withHeaders($this->apiKeyHeaders())
            ->getJson(route('news.show', ['news' => $news->id]))
            ->assertOk()
            ->assertJsonStructure([
                'data' => ['id', 'title', 'summary', 'content', 'published_at', 'image_url', 'language'],
            ])
            ->assertJsonPath('data.id', $news->id)
            ->assertJsonPath('data.content', 'Full body content.');
    }

    public function test_it_returns_404_for_unpublished_news(): void
    {
        $news = News::factory()->create([
            'published_at' => null,
        ]);

        $this->withHeaders($this->apiKeyHeaders())
            ->getJson(route('news.show', ['news' => $news->id]))
            ->assertNotFound();
    }

    public function test_it_returns_404_for_future_dated_news(): void
    {
        $news = News::factory()->create([
            'published_at' => CarbonImmutable::now()->addDays(2),
        ]);

        $this->withHeaders($this->apiKeyHeaders())
            ->getJson(route('news.show', ['news' => $news->id]))
            ->assertNotFound();
    }

    public function test_it_returns_404_for_unknown_id(): void
    {
        $this->withHeaders($this->apiKeyHeaders())
            ->getJson(route('news.show', ['news' => 999_999]))
            ->assertNotFound();
    }

    public function test_it_rejects_missing_auth(): void
    {
        $news = News::factory()->create([
            'published_at' => CarbonImmutable::now()->subHour(),
        ]);

        $this->getJson(route('news.show', ['news' => $news->id]))
            ->assertUnauthorized();
    }
}

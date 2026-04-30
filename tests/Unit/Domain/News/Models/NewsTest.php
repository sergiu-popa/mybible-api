<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\News\Models;

use App\Domain\News\Models\News;
use App\Domain\News\QueryBuilders\NewsQueryBuilder;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

final class NewsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_casts_published_at_to_a_carbon_datetime(): void
    {
        $news = News::factory()->publishedAt(CarbonImmutable::create(2026, 4, 23, 10))->create();

        $this->assertInstanceOf(Carbon::class, $news->published_at);
        $this->assertSame('2026-04-23 10:00:00', $news->published_at->toDateTimeString());
    }

    public function test_it_uses_the_custom_query_builder(): void
    {
        $this->assertInstanceOf(NewsQueryBuilder::class, News::query());
    }

    public function test_it_is_mass_assignable_with_the_full_column_surface(): void
    {
        $news = News::create([
            'language' => 'en',
            'title' => 'hello',
            'summary' => 'summary',
            'content' => 'content',
            'image_url' => 'news/1.png',
            'published_at' => CarbonImmutable::create(2026, 4, 23, 10),
        ]);

        $this->assertSame('hello', $news->title);
        $this->assertSame('news/1.png', $news->image_url);
        $this->assertNotNull($news->published_at);
    }
}

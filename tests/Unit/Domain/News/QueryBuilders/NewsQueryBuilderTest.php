<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\News\QueryBuilders;

use App\Domain\News\Models\News;
use App\Domain\Shared\Enums\Language;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class NewsQueryBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_published_excludes_unpublished_and_future_rows(): void
    {
        /** @var CarbonImmutable $now */
        $now = CarbonImmutable::create(2026, 4, 23, 12);

        News::factory()->publishedAt($now->subDay())->create(['title' => 'past']);
        News::factory()->unpublished()->create(['title' => 'draft']);
        News::factory()->scheduledFor($now->addDay())->create(['title' => 'future']);

        $titles = News::query()
            ->published($now)
            ->pluck('title')
            ->all();

        $this->assertSame(['past'], $titles);
    }

    public function test_for_language_narrows_to_matching_rows(): void
    {
        News::factory()->forLanguage(Language::En)->create(['title' => 'en-1']);
        News::factory()->forLanguage(Language::Ro)->create(['title' => 'ro-1']);
        News::factory()->forLanguage(Language::Ro)->create(['title' => 'ro-2']);

        $titles = News::query()
            ->forLanguage(Language::Ro)
            ->orderBy('title')
            ->pluck('title')
            ->all();

        $this->assertSame(['ro-1', 'ro-2'], $titles);
    }

    public function test_newest_first_orders_by_published_at_then_id_descending(): void
    {
        /** @var CarbonImmutable $now */
        $now = CarbonImmutable::create(2026, 4, 23, 12);

        $a = News::factory()->publishedAt($now->subDay())->create(['title' => 'a']);
        $b = News::factory()->publishedAt($now)->create(['title' => 'b']);
        $c = News::factory()->publishedAt($now)->create(['title' => 'c']);

        $ids = News::query()
            ->newestFirst()
            ->pluck('id')
            ->all();

        // Same published_at → highest id first; earlier published_at comes last.
        $this->assertSame([$c->id, $b->id, $a->id], $ids);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Devotional\QueryBuilders;

use App\Domain\Devotional\Enums\DevotionalType;
use App\Domain\Devotional\Models\Devotional;
use App\Domain\Shared\Enums\Language;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class DevotionalQueryBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_on_date_matches_exact_day(): void
    {
        $match = Devotional::factory()->create([
            'date' => '2026-04-22',
            'language' => Language::Ro->value,
            'type' => DevotionalType::Adults,
        ]);

        Devotional::factory()->create([
            'date' => '2026-04-21',
            'language' => Language::Ro->value,
            'type' => DevotionalType::Adults,
        ]);

        $found = Devotional::query()
            ->forLanguage(Language::Ro)
            ->ofType(DevotionalType::Adults)
            ->onDate(CarbonImmutable::parse('2026-04-22'))
            ->first();

        $this->assertNotNull($found);
        $this->assertTrue($match->is($found));
    }

    public function test_within_range_filters_both_bounds(): void
    {
        Devotional::factory()->create(['date' => '2026-03-01', 'language' => 'ro', 'type' => DevotionalType::Adults]);
        Devotional::factory()->create(['date' => '2026-03-10', 'language' => 'ro', 'type' => DevotionalType::Adults]);
        Devotional::factory()->create(['date' => '2026-03-20', 'language' => 'ro', 'type' => DevotionalType::Adults]);
        Devotional::factory()->create(['date' => '2026-04-01', 'language' => 'ro', 'type' => DevotionalType::Adults]);

        $dates = Devotional::query()
            ->forLanguage(Language::Ro)
            ->ofType(DevotionalType::Adults)
            ->withinRange(
                CarbonImmutable::parse('2026-03-05'),
                CarbonImmutable::parse('2026-03-25'),
            )
            ->pluck('date')
            ->map(fn ($d) => $d->format('Y-m-d'))
            ->all();

        $this->assertSame(['2026-03-10', '2026-03-20'], $dates);
    }

    public function test_within_range_from_only(): void
    {
        Devotional::factory()->create(['date' => '2026-03-01', 'language' => 'ro', 'type' => DevotionalType::Adults]);
        Devotional::factory()->create(['date' => '2026-03-15', 'language' => 'ro', 'type' => DevotionalType::Adults]);

        $count = Devotional::query()
            ->forLanguage(Language::Ro)
            ->ofType(DevotionalType::Adults)
            ->withinRange(CarbonImmutable::parse('2026-03-10'), null)
            ->count();

        $this->assertSame(1, $count);
    }

    public function test_within_range_to_only(): void
    {
        Devotional::factory()->create(['date' => '2026-03-01', 'language' => 'ro', 'type' => DevotionalType::Adults]);
        Devotional::factory()->create(['date' => '2026-03-15', 'language' => 'ro', 'type' => DevotionalType::Adults]);

        $count = Devotional::query()
            ->forLanguage(Language::Ro)
            ->ofType(DevotionalType::Adults)
            ->withinRange(null, CarbonImmutable::parse('2026-03-10'))
            ->count();

        $this->assertSame(1, $count);
    }

    public function test_published_up_to_excludes_future_rows(): void
    {
        Devotional::factory()->create(['date' => '2026-04-22', 'language' => 'ro', 'type' => DevotionalType::Adults]);
        Devotional::factory()->create(['date' => '2026-04-23', 'language' => 'ro', 'type' => DevotionalType::Adults]);

        $count = Devotional::query()
            ->forLanguage(Language::Ro)
            ->ofType(DevotionalType::Adults)
            ->publishedUpTo(CarbonImmutable::parse('2026-04-22'))
            ->count();

        $this->assertSame(1, $count);
    }

    public function test_newest_first_orders_by_date_descending(): void
    {
        Devotional::factory()->create(['date' => '2026-04-01', 'language' => 'ro', 'type' => DevotionalType::Adults]);
        Devotional::factory()->create(['date' => '2026-04-22', 'language' => 'ro', 'type' => DevotionalType::Adults]);
        Devotional::factory()->create(['date' => '2026-04-10', 'language' => 'ro', 'type' => DevotionalType::Adults]);

        $dates = Devotional::query()
            ->forLanguage(Language::Ro)
            ->ofType(DevotionalType::Adults)
            ->newestFirst()
            ->pluck('date')
            ->map(fn ($d) => $d->format('Y-m-d'))
            ->all();

        $this->assertSame(['2026-04-22', '2026-04-10', '2026-04-01'], $dates);
    }
}

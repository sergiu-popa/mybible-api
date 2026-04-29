<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Devotional\Actions;

use App\Domain\Devotional\Actions\FetchDevotionalAction;
use App\Domain\Devotional\DataTransferObjects\FetchDevotionalData;
use App\Domain\Devotional\Enums\DevotionalType;
use App\Domain\Devotional\Models\Devotional;
use App\Domain\Shared\Enums\Language;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class FetchDevotionalActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_the_devotional_for_the_requested_tuple(): void
    {
        $expected = Devotional::factory()
            ->adults()
            ->forLanguage(Language::Ro)
            ->onDate(CarbonImmutable::parse('2026-04-22'))
            ->create();

        Devotional::factory()->kids()->forLanguage(Language::Ro)->onDate(CarbonImmutable::parse('2026-04-22'))->create();
        Devotional::factory()->adults()->forLanguage(Language::Hu)->onDate(CarbonImmutable::parse('2026-04-22'))->create();

        $result = app(FetchDevotionalAction::class)->execute(new FetchDevotionalData(
            language: Language::Ro,
            type: DevotionalType::Adults,
            date: CarbonImmutable::parse('2026-04-22'),
        ));

        $this->assertSame($expected->id, $result['data']['id']);
    }

    public function test_it_throws_model_not_found_when_no_devotional_matches(): void
    {
        $this->expectException(ModelNotFoundException::class);

        app(FetchDevotionalAction::class)->execute(new FetchDevotionalData(
            language: Language::Ro,
            type: DevotionalType::Adults,
            date: CarbonImmutable::parse('2026-04-22'),
        ));
    }

    public function test_it_does_not_fall_back_across_languages(): void
    {
        Devotional::factory()
            ->adults()
            ->forLanguage(Language::Hu)
            ->onDate(CarbonImmutable::parse('2026-04-22'))
            ->create();

        $this->expectException(ModelNotFoundException::class);

        app(FetchDevotionalAction::class)->execute(new FetchDevotionalData(
            language: Language::Ro,
            type: DevotionalType::Adults,
            date: CarbonImmutable::parse('2026-04-22'),
        ));
    }
}

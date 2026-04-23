<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Olympiad\Actions;

use App\Domain\Olympiad\Actions\ListOlympiadThemesAction;
use App\Domain\Olympiad\DataTransferObjects\OlympiadThemeFilter;
use App\Domain\Olympiad\Models\OlympiadQuestion;
use App\Domain\Shared\Enums\Language;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ListOlympiadThemesActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_distinct_tuples_with_question_counts_for_language(): void
    {
        OlympiadQuestion::factory()->forTheme('GEN', 1, 3)->count(2)->create();
        OlympiadQuestion::factory()->forTheme('GEN', 5, 5)->count(3)->create();
        OlympiadQuestion::factory()->forTheme('EXO', 1, 2, Language::Ro)->count(4)->create();

        $filter = new OlympiadThemeFilter(Language::En, 50);

        $paginator = (new ListOlympiadThemesAction)->execute($filter);

        $this->assertSame(2, $paginator->total());

        $items = collect($paginator->items())
            ->map(fn (OlympiadQuestion $q): string => sprintf(
                '%s|%d-%d|%d',
                (string) $q->getAttribute('book'),
                (int) $q->getAttribute('chapters_from'),
                (int) $q->getAttribute('chapters_to'),
                (int) $q->getAttribute('question_count'),
            ))
            ->sort()
            ->values()
            ->all();

        $this->assertSame(['GEN|1-3|2', 'GEN|5-5|3'], $items);
    }

    public function test_paginates_per_page(): void
    {
        OlympiadQuestion::factory()->forTheme('GEN', 1, 3)->create();
        OlympiadQuestion::factory()->forTheme('GEN', 5, 5)->create();
        OlympiadQuestion::factory()->forTheme('EXO', 1, 1)->create();

        $paginator = (new ListOlympiadThemesAction)
            ->execute(new OlympiadThemeFilter(Language::En, 2));

        $this->assertSame(2, $paginator->perPage());
        $this->assertSame(3, $paginator->total());
        $this->assertCount(2, $paginator->items());
    }
}

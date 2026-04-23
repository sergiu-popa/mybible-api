<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Olympiad\QueryBuilders;

use App\Domain\Olympiad\Models\OlympiadQuestion;
use App\Domain\Reference\ChapterRange;
use App\Domain\Shared\Enums\Language;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class OlympiadQuestionQueryBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_for_language_filters_by_language(): void
    {
        OlympiadQuestion::factory()->forTheme('GEN', 1, 3)->count(2)->create();
        OlympiadQuestion::factory()->forTheme('GEN', 1, 3, Language::Ro)->create();

        $count = OlympiadQuestion::query()->forLanguage(Language::En)->count();

        $this->assertSame(2, $count);
    }

    public function test_for_book_filters_by_book(): void
    {
        OlympiadQuestion::factory()->forTheme('GEN', 1, 3)->create();
        OlympiadQuestion::factory()->forTheme('EXO', 1, 3)->create();

        $count = OlympiadQuestion::query()->forBook('GEN')->count();

        $this->assertSame(1, $count);
    }

    public function test_for_chapter_range_matches_exact_bounds(): void
    {
        OlympiadQuestion::factory()->forTheme('GEN', 1, 3)->create();
        OlympiadQuestion::factory()->forTheme('GEN', 1, 4)->create();
        OlympiadQuestion::factory()->forTheme('GEN', 2, 3)->create();

        $count = OlympiadQuestion::query()
            ->forChapterRange(new ChapterRange(1, 3))
            ->count();

        $this->assertSame(1, $count);
    }

    public function test_themes_groups_distinct_tuples_with_counts(): void
    {
        OlympiadQuestion::factory()->forTheme('GEN', 1, 3)->count(3)->create();
        OlympiadQuestion::factory()->forTheme('GEN', 5, 5)->count(2)->create();
        OlympiadQuestion::factory()->forTheme('EXO', 1, 2, Language::Ro)->count(4)->create();

        $rows = OlympiadQuestion::query()->themes()->get();

        $this->assertCount(3, $rows);

        $tuples = $rows
            ->map(function (OlympiadQuestion $row): string {
                $language = $row->getAttribute('language');

                return sprintf(
                    '%s|%d-%d|%s|%d',
                    (string) $row->getAttribute('book'),
                    (int) $row->getAttribute('chapters_from'),
                    (int) $row->getAttribute('chapters_to'),
                    $language instanceof Language ? $language->value : (string) $language,
                    (int) $row->getAttribute('question_count'),
                );
            })
            ->sort()
            ->values()
            ->all();

        $this->assertSame(
            ['EXO|1-2|ro|4', 'GEN|1-3|en|3', 'GEN|5-5|en|2'],
            $tuples,
        );
    }
}

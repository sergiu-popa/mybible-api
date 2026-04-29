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

        $payload = app(ListOlympiadThemesAction::class)->execute($filter, 1);

        $this->assertSame(2, $payload['meta']['total']);

        $rows = [];
        /** @var array<int, array<string, mixed>> $data */
        $data = $payload['data'];
        foreach ($data as $row) {
            $rows[] = sprintf(
                '%s|%d-%d|%d',
                (string) $row['book'],
                (int) $row['chapters_from'],
                (int) $row['chapters_to'],
                (int) $row['question_count'],
            );
        }
        sort($rows);

        $this->assertSame(['GEN|1-3|2', 'GEN|5-5|3'], $rows);
    }

    public function test_paginates_per_page(): void
    {
        OlympiadQuestion::factory()->forTheme('GEN', 1, 3)->create();
        OlympiadQuestion::factory()->forTheme('GEN', 5, 5)->create();
        OlympiadQuestion::factory()->forTheme('EXO', 1, 1)->create();

        $payload = app(ListOlympiadThemesAction::class)
            ->execute(new OlympiadThemeFilter(Language::En, 2), 1);

        $this->assertSame(2, $payload['meta']['per_page']);
        $this->assertSame(3, $payload['meta']['total']);
        $this->assertCount(2, $payload['data']);
    }
}

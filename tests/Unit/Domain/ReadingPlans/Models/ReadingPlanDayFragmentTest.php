<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\ReadingPlans\Models;

use App\Domain\ReadingPlans\Enums\FragmentType;
use App\Domain\ReadingPlans\Models\ReadingPlanDay;
use App\Domain\ReadingPlans\Models\ReadingPlanDayFragment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

final class ReadingPlanDayFragmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_rejects_references_content_that_is_not_a_list(): void
    {
        $day = ReadingPlanDay::factory()->create();

        $this->expectException(InvalidArgumentException::class);

        ReadingPlanDayFragment::query()->create([
            'reading_plan_day_id' => $day->id,
            'position' => 1,
            'type' => FragmentType::References,
            'content' => ['en' => 'GEN.1-2'],
        ]);
    }

    public function test_it_rejects_references_content_with_non_string_entries(): void
    {
        $day = ReadingPlanDay::factory()->create();

        $this->expectException(InvalidArgumentException::class);

        ReadingPlanDayFragment::query()->create([
            'reading_plan_day_id' => $day->id,
            'position' => 1,
            'type' => FragmentType::References,
            'content' => ['GEN.1-2', 42],
        ]);
    }

    public function test_it_rejects_html_content_that_is_a_list(): void
    {
        $day = ReadingPlanDay::factory()->create();

        $this->expectException(InvalidArgumentException::class);

        ReadingPlanDayFragment::query()->create([
            'reading_plan_day_id' => $day->id,
            'position' => 1,
            'type' => FragmentType::Html,
            'content' => ['GEN.1-2', 'MAT.5'],
        ]);
    }

    public function test_it_rejects_html_content_with_non_string_values(): void
    {
        $day = ReadingPlanDay::factory()->create();

        $this->expectException(InvalidArgumentException::class);

        ReadingPlanDayFragment::query()->create([
            'reading_plan_day_id' => $day->id,
            'position' => 1,
            'type' => FragmentType::Html,
            'content' => ['en' => 123],
        ]);
    }

    public function test_it_accepts_valid_references_content(): void
    {
        $day = ReadingPlanDay::factory()->create();

        $fragment = ReadingPlanDayFragment::query()->create([
            'reading_plan_day_id' => $day->id,
            'position' => 1,
            'type' => FragmentType::References,
            'content' => ['GEN.1-2', 'MAT.5:27-48'],
        ]);

        $this->assertSame(['GEN.1-2', 'MAT.5:27-48'], $fragment->content);
    }

    public function test_it_accepts_valid_html_content(): void
    {
        $day = ReadingPlanDay::factory()->create();

        $fragment = ReadingPlanDayFragment::query()->create([
            'reading_plan_day_id' => $day->id,
            'position' => 1,
            'type' => FragmentType::Html,
            'content' => ['en' => '<p>Hi</p>', 'ro' => '<p>Salut</p>'],
        ]);

        $this->assertSame(['en' => '<p>Hi</p>', 'ro' => '<p>Salut</p>'], $fragment->content);
    }
}

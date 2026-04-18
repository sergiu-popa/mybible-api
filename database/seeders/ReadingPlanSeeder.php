<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\ReadingPlans\Enums\FragmentType;
use App\Domain\ReadingPlans\Enums\ReadingPlanStatus;
use App\Domain\ReadingPlans\Models\ReadingPlan;
use App\Domain\ReadingPlans\Models\ReadingPlanDay;
use App\Domain\ReadingPlans\Models\ReadingPlanDayFragment;
use Illuminate\Database\Seeder;

final class ReadingPlanSeeder extends Seeder
{
    public function run(): void
    {
        $plan = ReadingPlan::query()->create([
            'slug' => 'seven-days-of-wisdom',
            'name' => [
                'en' => 'Seven Days of Wisdom',
                'ro' => 'Șapte zile de înțelepciune',
            ],
            'description' => [
                'en' => 'A week-long plan walking through Proverbs.',
                'ro' => 'Un plan de o săptămână prin cartea Proverbelor.',
            ],
            'image' => [
                'en' => 'https://cdn.example.com/plans/seven-days-wisdom/cover-en.jpg',
                'ro' => 'https://cdn.example.com/plans/seven-days-wisdom/cover-ro.jpg',
            ],
            'thumbnail' => [
                'en' => 'https://cdn.example.com/plans/seven-days-wisdom/thumb-en.jpg',
                'ro' => 'https://cdn.example.com/plans/seven-days-wisdom/thumb-ro.jpg',
            ],
            'status' => ReadingPlanStatus::Published,
            'published_at' => now()->subDay(),
        ]);

        foreach (range(1, 7) as $position) {
            $day = ReadingPlanDay::query()->create([
                'reading_plan_id' => $plan->id,
                'position' => $position,
            ]);

            ReadingPlanDayFragment::query()->create([
                'reading_plan_day_id' => $day->id,
                'position' => 1,
                'type' => FragmentType::Html,
                'content' => [
                    'en' => "<p>Day {$position} introduction.</p>",
                    'ro' => "<p>Introducere ziua {$position}.</p>",
                ],
            ]);

            ReadingPlanDayFragment::query()->create([
                'reading_plan_day_id' => $day->id,
                'position' => 2,
                'type' => FragmentType::References,
                'content' => ["PRO.{$position}"],
            ]);
        }
    }
}

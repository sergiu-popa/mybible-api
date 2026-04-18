<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\ReadingPlans\Enums\FragmentType;
use App\Domain\ReadingPlans\Models\ReadingPlanDay;
use App\Domain\ReadingPlans\Models\ReadingPlanDayFragment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReadingPlanDayFragment>
 */
final class ReadingPlanDayFragmentFactory extends Factory
{
    protected $model = ReadingPlanDayFragment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reading_plan_day_id' => ReadingPlanDay::factory(),
            'position' => 1,
            'type' => FragmentType::Html,
            'content' => [
                'en' => '<p>English copy.</p>',
                'ro' => '<p>Copie în română.</p>',
            ],
        ];
    }

    public function html(): self
    {
        return $this->state(fn (): array => [
            'type' => FragmentType::Html,
            'content' => [
                'en' => '<p>English copy.</p>',
                'ro' => '<p>Copie în română.</p>',
            ],
        ]);
    }

    public function references(): self
    {
        return $this->state(fn (): array => [
            'type' => FragmentType::References,
            'content' => ['GEN.1-2', 'MAT.5:27-48'],
        ]);
    }
}

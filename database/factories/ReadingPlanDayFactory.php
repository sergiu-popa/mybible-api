<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\ReadingPlans\Models\ReadingPlan;
use App\Domain\ReadingPlans\Models\ReadingPlanDay;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReadingPlanDay>
 */
final class ReadingPlanDayFactory extends Factory
{
    protected $model = ReadingPlanDay::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reading_plan_id' => ReadingPlan::factory(),
            'position' => 1,
        ];
    }
}

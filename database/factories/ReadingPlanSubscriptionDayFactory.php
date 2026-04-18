<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\ReadingPlans\Models\ReadingPlanDay;
use App\Domain\ReadingPlans\Models\ReadingPlanSubscription;
use App\Domain\ReadingPlans\Models\ReadingPlanSubscriptionDay;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReadingPlanSubscriptionDay>
 */
final class ReadingPlanSubscriptionDayFactory extends Factory
{
    protected $model = ReadingPlanSubscriptionDay::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reading_plan_subscription_id' => ReadingPlanSubscription::factory(),
            'reading_plan_day_id' => ReadingPlanDay::factory(),
            'scheduled_date' => now()->toDateString(),
            'completed_at' => null,
        ];
    }

    public function pending(): self
    {
        return $this->state(fn (): array => [
            'completed_at' => null,
        ]);
    }

    public function completed(): self
    {
        return $this->state(fn (): array => [
            'completed_at' => now(),
        ]);
    }
}

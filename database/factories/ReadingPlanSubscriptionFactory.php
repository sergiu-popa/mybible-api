<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\ReadingPlans\Enums\SubscriptionStatus;
use App\Domain\ReadingPlans\Models\ReadingPlan;
use App\Domain\ReadingPlans\Models\ReadingPlanSubscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReadingPlanSubscription>
 */
final class ReadingPlanSubscriptionFactory extends Factory
{
    protected $model = ReadingPlanSubscription::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'reading_plan_id' => ReadingPlan::factory()->published(),
            'start_date' => now()->toDateString(),
            'status' => SubscriptionStatus::Active,
            'completed_at' => null,
        ];
    }

    public function active(): self
    {
        return $this->state(fn (): array => [
            'status' => SubscriptionStatus::Active,
            'completed_at' => null,
        ]);
    }

    public function completed(): self
    {
        return $this->state(fn (): array => [
            'status' => SubscriptionStatus::Completed,
            'completed_at' => now(),
        ]);
    }

    public function abandoned(): self
    {
        return $this->state(fn (): array => [
            'status' => SubscriptionStatus::Abandoned,
            'completed_at' => null,
        ]);
    }
}

<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Analytics\Models\AnalyticsDailyRollup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AnalyticsDailyRollup>
 */
final class AnalyticsDailyRollupFactory extends Factory
{
    protected $model = AnalyticsDailyRollup::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'date' => now()->toDateString(),
            'event_type' => 'devotional.viewed',
            'subject_type' => 'devotional',
            'subject_id' => fake()->numberBetween(1, 1000),
            'language' => 'ro',
            'event_count' => fake()->numberBetween(1, 1000),
            'unique_users' => fake()->numberBetween(0, 100),
            'unique_devices' => fake()->numberBetween(0, 200),
        ];
    }
}

<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Analytics\Models\AnalyticsUserActiveDaily;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AnalyticsUserActiveDaily>
 */
final class AnalyticsUserActiveDailyFactory extends Factory
{
    protected $model = AnalyticsUserActiveDaily::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'date' => now()->toDateString(),
            'user_id' => fake()->numberBetween(1, 100000),
        ];
    }
}

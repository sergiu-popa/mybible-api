<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Analytics\Models\AnalyticsDeviceActiveDaily;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AnalyticsDeviceActiveDaily>
 */
final class AnalyticsDeviceActiveDailyFactory extends Factory
{
    protected $model = AnalyticsDeviceActiveDaily::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'date' => now()->toDateString(),
            'device_id' => fake()->uuid(),
        ];
    }
}

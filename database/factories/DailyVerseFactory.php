<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Verses\Models\DailyVerse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DailyVerse>
 */
final class DailyVerseFactory extends Factory
{
    protected $model = DailyVerse::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'for_date' => fake()->unique()->date(),
            'reference' => 'GEN.1:1.VDC',
            'image_cdn_url' => null,
        ];
    }
}

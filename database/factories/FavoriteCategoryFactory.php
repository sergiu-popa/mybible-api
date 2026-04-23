<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Favorites\Models\FavoriteCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FavoriteCategory>
 */
final class FavoriteCategoryFactory extends Factory
{
    protected $model = FavoriteCategory::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->unique()->words(2, true),
            'color' => null,
        ];
    }
}

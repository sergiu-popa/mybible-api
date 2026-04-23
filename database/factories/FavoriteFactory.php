<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Favorites\Models\Favorite;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Favorite>
 */
final class FavoriteFactory extends Factory
{
    protected $model = Favorite::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'category_id' => null,
            'reference' => 'GEN.1:1.VDC',
            'note' => null,
        ];
    }
}

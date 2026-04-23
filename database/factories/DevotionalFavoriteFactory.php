<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Devotional\Models\Devotional;
use App\Domain\Devotional\Models\DevotionalFavorite;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DevotionalFavorite>
 */
final class DevotionalFavoriteFactory extends Factory
{
    protected $model = DevotionalFavorite::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'devotional_id' => Devotional::factory(),
            'created_at' => now(),
        ];
    }

    public function forUser(User $user): self
    {
        return $this->state(['user_id' => $user->id]);
    }
}

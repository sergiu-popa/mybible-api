<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Hymnal\Models\HymnalFavorite;
use App\Domain\Hymnal\Models\HymnalSong;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HymnalFavorite>
 */
final class HymnalFavoriteFactory extends Factory
{
    protected $model = HymnalFavorite::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'hymnal_song_id' => HymnalSong::factory(),
            'created_at' => now(),
        ];
    }
}

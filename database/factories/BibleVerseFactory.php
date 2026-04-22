<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Bible\Models\BibleBook;
use App\Domain\Bible\Models\BibleVerse;
use App\Domain\Bible\Models\BibleVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BibleVerse>
 */
final class BibleVerseFactory extends Factory
{
    protected $model = BibleVerse::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'bible_version_id' => BibleVersion::factory(),
            'bible_book_id' => BibleBook::factory(),
            'chapter' => fake()->numberBetween(1, 50),
            'verse' => fake()->numberBetween(1, 50),
            'text' => fake()->sentence(),
        ];
    }
}

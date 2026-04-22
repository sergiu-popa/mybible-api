<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Bible\Models\BibleBook;
use App\Domain\Bible\Models\BibleChapter;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BibleChapter>
 */
final class BibleChapterFactory extends Factory
{
    protected $model = BibleChapter::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'bible_book_id' => BibleBook::factory(),
            'number' => fake()->unique()->numberBetween(1, 10_000),
            'verse_count' => fake()->numberBetween(1, 176),
        ];
    }
}

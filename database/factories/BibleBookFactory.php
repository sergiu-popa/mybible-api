<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Bible\Models\BibleBook;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BibleBook>
 */
final class BibleBookFactory extends Factory
{
    protected $model = BibleBook::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $abbr = strtoupper(fake()->unique()->lexify('???'));
        $position = fake()->unique()->numberBetween(1, 10_000);

        return [
            'abbreviation' => $abbr,
            'testament' => $position <= 39 ? 'old' : 'new',
            'position' => $position,
            'chapter_count' => fake()->numberBetween(1, 150),
            'names' => [
                'en' => ucfirst(fake()->word()),
                'ro' => ucfirst(fake()->word()),
                'hu' => ucfirst(fake()->word()),
            ],
            'short_names' => [
                'en' => $abbr,
                'ro' => $abbr,
                'hu' => $abbr,
            ],
        ];
    }

    public function genesis(): self
    {
        return $this->state(fn (): array => [
            'abbreviation' => 'GEN',
            'testament' => 'old',
            'position' => 1,
            'chapter_count' => 50,
            'names' => [
                'en' => 'Genesis',
                'ro' => 'Geneza',
                'hu' => 'Mózes első könyve',
            ],
            'short_names' => [
                'en' => 'Gen',
                'ro' => 'Gen',
                'hu' => '1Móz',
            ],
        ]);
    }
}

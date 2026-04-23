<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Hymnal\Models\HymnalBook;
use App\Domain\Hymnal\Models\HymnalSong;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HymnalSong>
 */
final class HymnalSongFactory extends Factory
{
    protected $model = HymnalSong::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->sentence(3);

        return [
            'hymnal_book_id' => HymnalBook::factory(),
            'number' => fake()->numberBetween(1, 100_000),
            'title' => [
                'en' => $title,
                'ro' => 'RO: ' . $title,
            ],
            'author' => [
                'en' => fake()->name(),
                'ro' => fake()->name(),
            ],
            'composer' => [
                'en' => fake()->name(),
                'ro' => fake()->name(),
            ],
            'copyright' => [
                'en' => 'Public Domain',
                'ro' => 'Domeniu public',
            ],
            'stanzas' => [
                'en' => [
                    ['index' => 1, 'text' => fake()->paragraph(), 'is_chorus' => false],
                    ['index' => 2, 'text' => fake()->paragraph(), 'is_chorus' => true],
                ],
                'ro' => [
                    ['index' => 1, 'text' => fake()->paragraph(), 'is_chorus' => false],
                    ['index' => 2, 'text' => fake()->paragraph(), 'is_chorus' => true],
                ],
            ],
        ];
    }

    public function withStanzas(int $count): self
    {
        return $this->state(function () use ($count): array {
            $build = function (string $language) use ($count): array {
                $stanzas = [];
                for ($i = 1; $i <= $count; $i++) {
                    $stanzas[] = [
                        'index' => $i,
                        'text' => fake()->paragraph(),
                        'is_chorus' => false,
                    ];
                }

                return $stanzas;
            };

            return [
                'stanzas' => [
                    'en' => $build('en'),
                    'ro' => $build('ro'),
                ],
            ];
        });
    }
}

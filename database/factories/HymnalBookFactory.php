<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Hymnal\Models\HymnalBook;
use App\Domain\Shared\Enums\Language;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<HymnalBook>
 */
final class HymnalBookFactory extends Factory
{
    protected $model = HymnalBook::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->unique()->sentence(2);

        return [
            'slug' => Str::slug($title) . '-' . fake()->unique()->numberBetween(1, 1_000_000),
            'name' => [
                'en' => $title,
                'ro' => 'RO: ' . $title,
            ],
            'language' => Language::En->value,
            'position' => 0,
        ];
    }

    public function forLanguage(Language $language): self
    {
        return $this->state(fn (): array => [
            'language' => $language->value,
        ]);
    }
}

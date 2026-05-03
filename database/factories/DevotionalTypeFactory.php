<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Devotional\Models\DevotionalType;
use App\Domain\Shared\Enums\Language;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<DevotionalType>
 */
final class DevotionalTypeFactory extends Factory
{
    protected $model = DevotionalType::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $slug = 'type-' . Str::lower(Str::random(8));

        return [
            'slug' => $slug,
            'title' => Str::title(str_replace('-', ' ', $slug)),
            'position' => fake()->numberBetween(1, 50),
            'language' => null,
        ];
    }

    public function forLanguage(Language $language): self
    {
        return $this->state(['language' => $language->value]);
    }

    public function global(): self
    {
        return $this->state(['language' => null]);
    }

    public function adults(): self
    {
        return $this->state([
            'slug' => 'adults',
            'title' => 'Adults',
            'position' => 1,
            'language' => null,
        ]);
    }

    public function kids(): self
    {
        return $this->state([
            'slug' => 'kids',
            'title' => 'Kids',
            'position' => 2,
            'language' => null,
        ]);
    }
}

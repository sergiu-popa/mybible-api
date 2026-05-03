<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\EducationalResources\Models\ResourceBook;
use App\Domain\Shared\Enums\Language;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ResourceBook>
 */
final class ResourceBookFactory extends Factory
{
    protected $model = ResourceBook::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = (string) fake()->unique()->sentence(3);

        return [
            'slug' => Str::slug($name) . '-' . fake()->unique()->numberBetween(1, 1_000_000),
            'name' => $name,
            'language' => Language::Ro->value,
            'description' => fake()->paragraph(),
            'position' => 0,
            'is_published' => false,
            'published_at' => null,
            'cover_image_url' => null,
            'author' => fake()->name(),
        ];
    }

    public function published(): self
    {
        return $this->state(fn (): array => [
            'is_published' => true,
            'published_at' => now(),
        ]);
    }

    public function draft(): self
    {
        return $this->state(fn (): array => [
            'is_published' => false,
            'published_at' => null,
        ]);
    }

    public function forLanguage(Language $language): self
    {
        return $this->state(fn (): array => [
            'language' => $language->value,
        ]);
    }
}

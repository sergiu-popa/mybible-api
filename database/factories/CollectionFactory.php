<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Collections\Models\Collection;
use App\Domain\Shared\Enums\Language;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Collection>
 */
final class CollectionFactory extends Factory
{
    protected $model = Collection::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        /** @var array<int, string> $words */
        $words = fake()->words(2);
        $name = ucfirst(implode(' ', $words));

        return [
            'slug' => Str::slug($name) . '-' . Str::lower(Str::random(6)),
            'name' => $name,
            'language' => Language::Ro->value,
            'position' => fake()->numberBetween(1, 100),
        ];
    }

    public function forLanguage(Language $language): self
    {
        return $this->state(['language' => $language->value]);
    }
}

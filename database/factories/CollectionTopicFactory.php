<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Collections\Models\CollectionTopic;
use App\Domain\Shared\Enums\Language;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CollectionTopic>
 */
final class CollectionTopicFactory extends Factory
{
    protected $model = CollectionTopic::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'language' => Language::En->value,
            'name' => fake()->unique()->sentence(3),
            'description' => fake()->optional()->paragraph(),
            'position' => fake()->numberBetween(0, 100),
        ];
    }

    public function english(): self
    {
        return $this->state(fn (): array => ['language' => Language::En->value]);
    }

    public function romanian(): self
    {
        return $this->state(fn (): array => ['language' => Language::Ro->value]);
    }

    public function hungarian(): self
    {
        return $this->state(fn (): array => ['language' => Language::Hu->value]);
    }
}

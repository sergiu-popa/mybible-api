<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\EducationalResources\Enums\ResourceType;
use App\Domain\EducationalResources\Models\EducationalResource;
use App\Domain\EducationalResources\Models\ResourceCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<EducationalResource>
 */
final class EducationalResourceFactory extends Factory
{
    protected $model = EducationalResource::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->unique()->sentence(4);

        return [
            'uuid' => (string) Str::uuid(),
            'resource_category_id' => ResourceCategory::factory(),
            'type' => fake()->randomElement(ResourceType::cases()),
            'title' => [
                'en' => $title,
                'ro' => 'RO: ' . $title,
            ],
            'summary' => [
                'en' => fake()->sentence(),
                'ro' => 'RO: ' . fake()->sentence(),
            ],
            'content' => [
                'en' => fake()->paragraph(),
                'ro' => 'RO: ' . fake()->paragraph(),
            ],
            'thumbnail_path' => null,
            'media_path' => null,
            'author' => fake()->name(),
            'published_at' => fake()->dateTimeBetween('-1 year', '-1 day'),
        ];
    }

    public function ofType(ResourceType $type): self
    {
        return $this->state(fn (): array => [
            'type' => $type,
        ]);
    }

    public function forCategory(ResourceCategory $category): self
    {
        return $this->state(fn (): array => [
            'resource_category_id' => $category->id,
        ]);
    }
}

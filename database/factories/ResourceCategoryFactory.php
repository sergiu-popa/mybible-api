<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\EducationalResources\Models\ResourceCategory;
use App\Domain\Shared\Enums\Language;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ResourceCategory>
 */
final class ResourceCategoryFactory extends Factory
{
    protected $model = ResourceCategory::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        /** @var string $title */
        $title = fake()->unique()->words(2, true);
        $title = ucfirst($title);

        return [
            'name' => [
                'en' => $title,
                'ro' => 'RO: ' . $title,
                'hu' => 'HU: ' . $title,
            ],
            'description' => [
                'en' => fake()->sentence(),
                'ro' => 'RO: ' . fake()->sentence(),
            ],
            'language' => fake()->randomElement([
                Language::En->value,
                Language::Ro->value,
                Language::Hu->value,
            ]),
        ];
    }

    public function forLanguage(Language $language): self
    {
        return $this->state(fn (): array => [
            'language' => $language->value,
        ]);
    }
}

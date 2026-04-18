<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\ReadingPlans\Enums\ReadingPlanStatus;
use App\Domain\ReadingPlans\Models\ReadingPlan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ReadingPlan>
 */
final class ReadingPlanFactory extends Factory
{
    protected $model = ReadingPlan::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->unique()->sentence(3);

        return [
            'slug' => Str::slug($title) . '-' . fake()->unique()->numberBetween(1, 1_000_000),
            'name' => [
                'en' => $title,
                'ro' => 'RO: ' . $title,
            ],
            'description' => [
                'en' => fake()->paragraph(),
                'ro' => 'RO: ' . fake()->paragraph(),
            ],
            'image' => [
                'en' => 'https://cdn.example.com/plans/image-en.jpg',
                'ro' => 'https://cdn.example.com/plans/image-ro.jpg',
            ],
            'thumbnail' => [
                'en' => 'https://cdn.example.com/plans/thumb-en.jpg',
                'ro' => 'https://cdn.example.com/plans/thumb-ro.jpg',
            ],
            'status' => ReadingPlanStatus::Draft,
            'published_at' => null,
        ];
    }

    public function published(): self
    {
        return $this->state(fn (): array => [
            'status' => ReadingPlanStatus::Published,
            'published_at' => now()->subDay(),
        ]);
    }

    public function draft(): self
    {
        return $this->state(fn (): array => [
            'status' => ReadingPlanStatus::Draft,
            'published_at' => null,
        ]);
    }
}

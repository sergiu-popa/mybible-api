<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\EducationalResources\Models\ResourceBook;
use App\Domain\EducationalResources\Models\ResourceBookChapter;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ResourceBookChapter>
 */
final class ResourceBookChapterFactory extends Factory
{
    protected $model = ResourceBookChapter::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'resource_book_id' => ResourceBook::factory(),
            'position' => fake()->unique()->numberBetween(1, 1_000_000),
            'title' => fake()->sentence(4),
            'content' => fake()->paragraph(),
            'audio_cdn_url' => null,
            'audio_embed' => null,
            'duration_seconds' => null,
        ];
    }

    public function withAudio(): self
    {
        return $this->state(fn (): array => [
            'audio_cdn_url' => 'https://cdn.example.com/audio/' . fake()->uuid() . '.mp3',
            'duration_seconds' => fake()->numberBetween(60, 3600),
        ]);
    }

    public function forBook(ResourceBook $book): self
    {
        return $this->state(fn (): array => [
            'resource_book_id' => $book->id,
        ]);
    }
}

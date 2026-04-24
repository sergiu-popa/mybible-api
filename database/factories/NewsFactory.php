<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\News\Models\News;
use App\Domain\Shared\Enums\Language;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<News>
 */
final class NewsFactory extends Factory
{
    protected $model = News::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'language' => Language::En->value,
            'title' => fake()->sentence(5),
            'summary' => fake()->paragraph(2),
            'content' => fake()->paragraphs(3, true),
            'image_path' => null,
            'published_at' => CarbonImmutable::now()->subDays(fake()->numberBetween(0, 30)),
        ];
    }

    public function forLanguage(Language $language): self
    {
        return $this->state(fn (): array => ['language' => $language->value]);
    }

    public function publishedAt(CarbonImmutable $when): self
    {
        return $this->state(fn (): array => ['published_at' => $when]);
    }

    public function unpublished(): self
    {
        return $this->state(fn (): array => ['published_at' => null]);
    }

    public function scheduledFor(CarbonImmutable $when): self
    {
        return $this->state(fn (): array => ['published_at' => $when]);
    }

    public function withImage(string $path = 'news/sample.png'): self
    {
        return $this->state(fn (): array => ['image_path' => $path]);
    }
}

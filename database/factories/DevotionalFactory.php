<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Devotional\Enums\DevotionalType;
use App\Domain\Devotional\Models\Devotional;
use App\Domain\Shared\Enums\Language;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Devotional>
 */
final class DevotionalFactory extends Factory
{
    protected $model = Devotional::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'date' => fake()->date(),
            'language' => Language::Ro->value,
            'type' => DevotionalType::Adults,
            'title' => fake()->sentence(4),
            'content' => fake()->paragraphs(3, true),
            'passage' => fake()->boolean() ? 'JHN.3:16' : null,
            'author' => fake()->boolean() ? fake()->name() : null,
        ];
    }

    public function adults(): self
    {
        return $this->state(['type' => DevotionalType::Adults]);
    }

    public function kids(): self
    {
        return $this->state(['type' => DevotionalType::Kids]);
    }

    public function forLanguage(Language $language): self
    {
        return $this->state(['language' => $language->value]);
    }

    public function onDate(CarbonImmutable $date): self
    {
        return $this->state(['date' => $date->toDateString()]);
    }
}

<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Devotional\Models\Devotional;
use App\Domain\Devotional\Models\DevotionalType;
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
            'type_id' => fn (): int => $this->resolveTypeId('adults'),
            'type' => 'adults',
            'title' => fake()->sentence(4),
            'content' => fake()->paragraphs(3, true),
            'audio_cdn_url' => null,
            'audio_embed' => null,
            'video_embed' => null,
            'passage' => 'JHN.3:16',
            'author' => fake()->name(),
        ];
    }

    public function adults(): self
    {
        return $this->state(fn (): array => [
            'type_id' => $this->resolveTypeId('adults'),
            'type' => 'adults',
        ]);
    }

    public function kids(): self
    {
        return $this->state(fn (): array => [
            'type_id' => $this->resolveTypeId('kids'),
            'type' => 'kids',
        ]);
    }

    public function ofType(DevotionalType $type): self
    {
        return $this->state([
            'type_id' => $type->id,
            'type' => $type->slug,
        ]);
    }

    public function forLanguage(Language $language): self
    {
        return $this->state(['language' => $language->value]);
    }

    public function onDate(CarbonImmutable $date): self
    {
        return $this->state(['date' => $date->toDateString()]);
    }

    public function withoutPassage(): self
    {
        return $this->state(['passage' => null]);
    }

    public function anonymous(): self
    {
        return $this->state(['author' => null]);
    }

    private function resolveTypeId(string $slug): int
    {
        $existing = DevotionalType::query()->where('slug', $slug)->whereNull('language')->first();

        if ($existing !== null) {
            return $existing->id;
        }

        $titles = ['adults' => 'Adults', 'kids' => 'Kids'];
        $positions = ['adults' => 1, 'kids' => 2];

        return DevotionalType::factory()->create([
            'slug' => $slug,
            'title' => $titles[$slug] ?? ucfirst($slug),
            'position' => $positions[$slug] ?? 0,
            'language' => null,
        ])->id;
    }
}

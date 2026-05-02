<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Commentary\Models\Commentary;
use App\Domain\Shared\Enums\Language;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Commentary>
 */
final class CommentaryFactory extends Factory
{
    protected $model = Commentary::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        /** @var string $title */
        $title = fake()->unique()->words(2, true);
        $abbreviation = strtoupper(fake()->unique()->lexify('???'));

        return [
            'slug' => Str::slug($abbreviation) . '-' . fake()->unique()->numberBetween(1, 1_000_000),
            'name' => [
                'en' => 'Commentary on ' . ucfirst($title),
                'ro' => 'Comentariu ' . $title,
            ],
            'abbreviation' => $abbreviation,
            'language' => Language::Ro->value,
            'is_published' => false,
            'source_commentary_id' => null,
        ];
    }

    public function published(): self
    {
        return $this->state(fn (): array => [
            'is_published' => true,
        ]);
    }

    public function draft(): self
    {
        return $this->state(fn (): array => [
            'is_published' => false,
        ]);
    }

    public function forLanguage(Language $language): self
    {
        return $this->state(fn (): array => [
            'language' => $language->value,
        ]);
    }

    public function translationOf(Commentary $source): self
    {
        return $this->state(fn (): array => [
            'source_commentary_id' => $source->id,
        ]);
    }
}

<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Olympiad\Models\OlympiadQuestion;
use App\Domain\Reference\Data\BibleBookCatalog;
use App\Domain\Shared\Enums\Language;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<OlympiadQuestion>
 */
final class OlympiadQuestionFactory extends Factory
{
    protected $model = OlympiadQuestion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $book = fake()->randomElement(array_keys(BibleBookCatalog::BOOKS));
        $from = fake()->numberBetween(1, 3);
        $to = $from + fake()->numberBetween(0, 2);

        return [
            'uuid' => (string) Str::uuid(),
            'book' => $book,
            'chapters_from' => $from,
            'chapters_to' => $to,
            'chapter' => null,
            'verse' => null,
            'language' => Language::En,
            'question' => rtrim(fake()->sentence(), '.') . '?',
            'explanation' => fake()->optional()->sentence(),
            'is_reviewed' => false,
        ];
    }

    public function forTheme(string $book, int $from, int $to, Language $language = Language::En): self
    {
        return $this->state(fn (): array => [
            'book' => $book,
            'chapters_from' => $from,
            'chapters_to' => $to,
            'language' => $language,
        ]);
    }
}

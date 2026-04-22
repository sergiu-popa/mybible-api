<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Olympiad\Models\OlympiadQuestion;
use App\Domain\Reference\Data\BibleBookCatalog;
use App\Domain\Shared\Enums\Language;
use Illuminate\Database\Eloquent\Factories\Factory;

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
            'book' => $book,
            'chapters_from' => $from,
            'chapters_to' => $to,
            'language' => Language::En,
            'question' => rtrim(fake()->sentence(), '.') . '?',
            'explanation' => fake()->optional()->sentence(),
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

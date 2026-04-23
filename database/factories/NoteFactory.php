<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Notes\Models\Note;
use App\Domain\Reference\Data\BibleBookCatalog;
use App\Domain\Reference\Formatter\ReferenceFormatter;
use App\Domain\Reference\Reference;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Note>
 */
final class NoteFactory extends Factory
{
    protected $model = Note::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        /** @var string $book */
        $book = fake()->randomElement(array_keys(BibleBookCatalog::BOOKS));
        $maxChapter = BibleBookCatalog::maxChapter($book);
        $chapter = fake()->numberBetween(1, $maxChapter);
        $verse = fake()->numberBetween(1, 30);

        $reference = new Reference(
            book: $book,
            chapter: $chapter,
            verses: [$verse],
            version: 'VDC',
        );

        $canonical = (new ReferenceFormatter)->toCanonical($reference);

        return [
            'user_id' => User::factory(),
            'reference' => $canonical,
            'book' => $book,
            'content' => fake()->paragraph(),
        ];
    }

    public function forBook(string $book): self
    {
        $book = strtoupper($book);
        $maxChapter = BibleBookCatalog::maxChapter($book);
        $chapter = fake()->numberBetween(1, $maxChapter);
        $verse = fake()->numberBetween(1, 30);

        $reference = new Reference(
            book: $book,
            chapter: $chapter,
            verses: [$verse],
            version: 'VDC',
        );

        $canonical = (new ReferenceFormatter)->toCanonical($reference);

        return $this->state(fn (): array => [
            'book' => $book,
            'reference' => $canonical,
        ]);
    }
}

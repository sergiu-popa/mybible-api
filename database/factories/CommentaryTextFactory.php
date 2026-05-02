<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Commentary\Models\Commentary;
use App\Domain\Commentary\Models\CommentaryText;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CommentaryText>
 */
final class CommentaryTextFactory extends Factory
{
    protected $model = CommentaryText::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $position = fake()->numberBetween(1, 1000);

        return [
            'commentary_id' => Commentary::factory(),
            'book' => 'GEN',
            'chapter' => 1,
            'position' => $position,
            'verse_from' => $position,
            'verse_to' => $position,
            'verse_label' => (string) $position,
            'content' => fake()->paragraph(),
        ];
    }

    public function forVerseRange(int $from, int $to): self
    {
        return $this->state(fn (): array => [
            'verse_from' => $from,
            'verse_to' => $to,
            'verse_label' => $from === $to ? (string) $from : "{$from}-{$to}",
        ]);
    }

    public function openEnded(int $from): self
    {
        return $this->state(fn (): array => [
            'verse_from' => $from,
            'verse_to' => null,
            'verse_label' => (string) $from . '+',
        ]);
    }
}

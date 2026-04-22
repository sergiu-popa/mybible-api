<?php

declare(strict_types=1);

namespace App\Domain\Reference;

use App\Domain\Reference\Exceptions\InvalidReferenceException;

final readonly class Reference
{
    /**
     * @param  array<int, int>  $verses  Pre-expanded, sorted, unique list of individual verse numbers (empty for whole chapter).
     */
    public function __construct(
        public string $book,
        public int $chapter,
        public array $verses = [],
        public ?string $version = null,
    ) {
        $previous = 0;

        foreach ($verses as $verse) {
            if ($verse < 1) {
                throw InvalidReferenceException::unparseable(
                    $this->describe(),
                    'verses must be positive integers',
                );
            }

            if ($verse <= $previous) {
                throw InvalidReferenceException::unparseable(
                    $this->describe(),
                    'verses must be ascending and unique',
                );
            }

            $previous = $verse;
        }
    }

    public function isWholeChapter(): bool
    {
        return $this->verses === [];
    }

    public function isSingleVerse(): bool
    {
        return count($this->verses) === 1;
    }

    public function isRange(): bool
    {
        return count($this->verses) > 1;
    }

    public function getVerse(): int
    {
        return $this->verses[0] ?? 0;
    }

    private function describe(): string
    {
        return sprintf('%s.%d', $this->book, $this->chapter);
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Reference;

use App\Domain\Reference\Exceptions\InvalidReferenceException;

/**
 * Cross-chapter passage range expressed as `BOOK.CH:V-CH:V[.VER]`.
 *
 * Single-chapter ranges and whole-chapter references stay on
 * {@see Reference}; this VO carries the additional `endChapter` axis
 * that {@see Reference} cannot represent.
 */
final readonly class VerseRange
{
    public function __construct(
        public string $book,
        public int $startChapter,
        public int $startVerse,
        public int $endChapter,
        public int $endVerse,
        public ?string $version = null,
    ) {
        if ($this->startChapter < 1 || $this->endChapter < 1) {
            throw InvalidReferenceException::unparseable(
                $this->canonical(),
                'chapter must be a positive integer',
            );
        }

        if ($this->startVerse < 1 || $this->endVerse < 1) {
            throw InvalidReferenceException::unparseable(
                $this->canonical(),
                'verse must be a positive integer',
            );
        }

        if ($this->endChapter < $this->startChapter) {
            throw InvalidReferenceException::unparseable(
                $this->canonical(),
                'cross-chapter range must end after it starts',
            );
        }

        if ($this->endChapter === $this->startChapter && $this->endVerse <= $this->startVerse) {
            throw InvalidReferenceException::unparseable(
                $this->canonical(),
                'cross-chapter range must end after it starts',
            );
        }
    }

    public function canonical(): string
    {
        $core = sprintf(
            '%s.%d:%d-%d:%d',
            $this->book,
            $this->startChapter,
            $this->startVerse,
            $this->endChapter,
            $this->endVerse,
        );

        return $this->version === null ? $core : sprintf('%s.%s', $core, $this->version);
    }
}

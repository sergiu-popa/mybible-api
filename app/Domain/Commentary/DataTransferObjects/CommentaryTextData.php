<?php

declare(strict_types=1);

namespace App\Domain\Commentary\DataTransferObjects;

final readonly class CommentaryTextData
{
    public function __construct(
        public string $book,
        public int $chapter,
        public int $position,
        public ?int $verseFrom,
        public ?int $verseTo,
        public ?string $verseLabel,
        public string $content,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function from(array $data): self
    {
        return new self(
            book: strtoupper((string) ($data['book'] ?? '')),
            chapter: (int) ($data['chapter'] ?? 0),
            position: (int) ($data['position'] ?? 0),
            verseFrom: isset($data['verse_from']) ? (int) $data['verse_from'] : null,
            verseTo: isset($data['verse_to']) ? (int) $data['verse_to'] : null,
            verseLabel: isset($data['verse_label']) ? (string) $data['verse_label'] : null,
            content: (string) ($data['content'] ?? ''),
        );
    }
}

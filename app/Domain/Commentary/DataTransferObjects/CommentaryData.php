<?php

declare(strict_types=1);

namespace App\Domain\Commentary\DataTransferObjects;

final readonly class CommentaryData
{
    /**
     * @param  array<string, string>  $name
     */
    public function __construct(
        public ?string $slug,
        public array $name,
        public string $abbreviation,
        public string $language,
        public ?int $sourceCommentaryId,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function from(array $data): self
    {
        /** @var array<string, string> $name */
        $name = $data['name'] ?? [];

        return new self(
            slug: isset($data['slug']) ? (string) $data['slug'] : null,
            name: $name,
            abbreviation: (string) ($data['abbreviation'] ?? ''),
            language: (string) ($data['language'] ?? ''),
            sourceCommentaryId: isset($data['source_commentary_id'])
                ? (int) $data['source_commentary_id']
                : null,
        );
    }
}

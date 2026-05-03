<?php

declare(strict_types=1);

namespace App\Domain\EducationalResources\DataTransferObjects;

final readonly class ResourceBookData
{
    public function __construct(
        public ?string $slug,
        public string $name,
        public string $language,
        public ?string $description,
        public ?string $coverImageUrl,
        public ?string $author,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function from(array $data): self
    {
        return new self(
            slug: isset($data['slug']) ? (string) $data['slug'] : null,
            name: (string) ($data['name'] ?? ''),
            language: (string) ($data['language'] ?? ''),
            description: isset($data['description']) ? (string) $data['description'] : null,
            coverImageUrl: isset($data['cover_image_url']) ? (string) $data['cover_image_url'] : null,
            author: isset($data['author']) ? (string) $data['author'] : null,
        );
    }
}

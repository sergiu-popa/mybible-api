<?php

declare(strict_types=1);

namespace App\Domain\SabbathSchool\DataTransferObjects;

/**
 * Partial DTO for PATCH /admin/.../segment-contents/{content}. Only
 * fields present in the validated payload are emitted by `toArray()`.
 */
final readonly class UpdateSegmentContentData
{
    /**
     * @param  array<int, string>  $present  validated keys actually supplied
     */
    public function __construct(
        public ?string $type,
        public ?string $title,
        public ?int $position,
        public ?string $content,
        public array $present,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function from(array $data): self
    {
        return new self(
            type: array_key_exists('type', $data) ? (string) $data['type'] : null,
            title: array_key_exists('title', $data) && $data['title'] !== null
                ? (string) $data['title']
                : null,
            position: array_key_exists('position', $data) ? (int) $data['position'] : null,
            content: array_key_exists('content', $data) ? (string) $data['content'] : null,
            present: array_keys($data),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $map = [
            'type' => $this->type,
            'title' => $this->title,
            'position' => $this->position,
            'content' => $this->content,
        ];

        return array_intersect_key($map, array_flip($this->present));
    }
}

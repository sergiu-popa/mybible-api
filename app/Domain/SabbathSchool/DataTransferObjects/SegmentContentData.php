<?php

declare(strict_types=1);

namespace App\Domain\SabbathSchool\DataTransferObjects;

final readonly class SegmentContentData
{
    public function __construct(
        public string $type,
        public ?string $title,
        public int $position,
        public string $content,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function from(array $data): self
    {
        return new self(
            type: (string) ($data['type'] ?? ''),
            title: isset($data['title']) ? (string) $data['title'] : null,
            position: (int) ($data['position'] ?? 0),
            content: (string) ($data['content'] ?? ''),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'title' => $this->title,
            'position' => $this->position,
            'content' => $this->content,
        ];
    }
}

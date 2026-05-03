<?php

declare(strict_types=1);

namespace App\Domain\EducationalResources\DataTransferObjects;

final readonly class ResourceBookChapterData
{
    public function __construct(
        public string $title,
        public string $content,
        public ?string $audioCdnUrl,
        public ?string $audioEmbed,
        public ?int $durationSeconds,
        public ?int $position,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function from(array $data): self
    {
        return new self(
            title: (string) ($data['title'] ?? ''),
            content: (string) ($data['content'] ?? ''),
            audioCdnUrl: isset($data['audio_cdn_url']) ? (string) $data['audio_cdn_url'] : null,
            audioEmbed: isset($data['audio_embed']) ? (string) $data['audio_embed'] : null,
            durationSeconds: isset($data['duration_seconds']) ? (int) $data['duration_seconds'] : null,
            position: isset($data['position']) ? (int) $data['position'] : null,
        );
    }
}

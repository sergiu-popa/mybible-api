<?php

declare(strict_types=1);

namespace App\Domain\SabbathSchool\DataTransferObjects;

use Carbon\CarbonImmutable;

/**
 * Partial DTO for PATCH /admin/.../lessons/{lesson}. Only fields that
 * were present in the validated payload are emitted by `toArray()`.
 */
final readonly class UpdateLessonData
{
    /**
     * @param  array<int, string>  $present  validated keys actually supplied
     */
    public function __construct(
        public ?string $language,
        public ?string $ageGroup,
        public ?string $title,
        public ?int $number,
        public ?CarbonImmutable $dateFrom,
        public ?CarbonImmutable $dateTo,
        public ?int $trimesterId,
        public ?string $memoryVerse,
        public ?string $imageCdnUrl,
        public ?CarbonImmutable $publishedAt,
        public array $present,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function from(array $data): self
    {
        return new self(
            language: array_key_exists('language', $data) ? (string) $data['language'] : null,
            ageGroup: array_key_exists('age_group', $data) ? (string) $data['age_group'] : null,
            title: array_key_exists('title', $data) ? (string) $data['title'] : null,
            number: array_key_exists('number', $data) ? (int) $data['number'] : null,
            dateFrom: array_key_exists('date_from', $data)
                ? CarbonImmutable::parse((string) $data['date_from'])
                : null,
            dateTo: array_key_exists('date_to', $data)
                ? CarbonImmutable::parse((string) $data['date_to'])
                : null,
            trimesterId: array_key_exists('trimester_id', $data) && $data['trimester_id'] !== null
                ? (int) $data['trimester_id']
                : null,
            memoryVerse: array_key_exists('memory_verse', $data) && $data['memory_verse'] !== null
                ? (string) $data['memory_verse']
                : null,
            imageCdnUrl: array_key_exists('image_cdn_url', $data) && $data['image_cdn_url'] !== null
                ? (string) $data['image_cdn_url']
                : null,
            publishedAt: array_key_exists('published_at', $data) && $data['published_at'] !== null
                ? CarbonImmutable::parse((string) $data['published_at'])
                : null,
            present: array_keys($data),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $map = [
            'language' => $this->language,
            'age_group' => $this->ageGroup,
            'title' => $this->title,
            'number' => $this->number,
            'date_from' => $this->dateFrom?->toDateString(),
            'date_to' => $this->dateTo?->toDateString(),
            'trimester_id' => $this->trimesterId,
            'memory_verse' => $this->memoryVerse,
            'image_cdn_url' => $this->imageCdnUrl,
            'published_at' => $this->publishedAt?->toDateTimeString(),
        ];

        return array_intersect_key($map, array_flip($this->present));
    }
}

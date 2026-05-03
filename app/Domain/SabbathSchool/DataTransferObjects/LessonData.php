<?php

declare(strict_types=1);

namespace App\Domain\SabbathSchool\DataTransferObjects;

use Carbon\CarbonImmutable;

final readonly class LessonData
{
    public function __construct(
        public string $language,
        public string $ageGroup,
        public string $title,
        public int $number,
        public CarbonImmutable $dateFrom,
        public CarbonImmutable $dateTo,
        public ?int $trimesterId,
        public ?string $memoryVerse,
        public ?string $imageCdnUrl,
        public ?CarbonImmutable $publishedAt,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function from(array $data): self
    {
        return new self(
            language: (string) ($data['language'] ?? ''),
            ageGroup: (string) ($data['age_group'] ?? ''),
            title: (string) ($data['title'] ?? ''),
            number: (int) ($data['number'] ?? 0),
            dateFrom: CarbonImmutable::parse((string) ($data['date_from'] ?? 'now')),
            dateTo: CarbonImmutable::parse((string) ($data['date_to'] ?? 'now')),
            trimesterId: isset($data['trimester_id']) ? (int) $data['trimester_id'] : null,
            memoryVerse: isset($data['memory_verse']) ? (string) $data['memory_verse'] : null,
            imageCdnUrl: isset($data['image_cdn_url']) ? (string) $data['image_cdn_url'] : null,
            publishedAt: isset($data['published_at'])
                ? CarbonImmutable::parse((string) $data['published_at'])
                : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'language' => $this->language,
            'age_group' => $this->ageGroup,
            'title' => $this->title,
            'number' => $this->number,
            'date_from' => $this->dateFrom->toDateString(),
            'date_to' => $this->dateTo->toDateString(),
            'trimester_id' => $this->trimesterId,
            'memory_verse' => $this->memoryVerse,
            'image_cdn_url' => $this->imageCdnUrl,
            'published_at' => $this->publishedAt?->toDateTimeString(),
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\SabbathSchool\DataTransferObjects;

use Carbon\CarbonImmutable;

final readonly class TrimesterData
{
    public function __construct(
        public string $year,
        public string $language,
        public string $ageGroup,
        public string $title,
        public int $number,
        public CarbonImmutable $dateFrom,
        public CarbonImmutable $dateTo,
        public ?string $imageCdnUrl,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function from(array $data): self
    {
        return new self(
            year: (string) ($data['year'] ?? ''),
            language: (string) ($data['language'] ?? ''),
            ageGroup: (string) ($data['age_group'] ?? ''),
            title: (string) ($data['title'] ?? ''),
            number: (int) ($data['number'] ?? 0),
            dateFrom: CarbonImmutable::parse((string) ($data['date_from'] ?? 'now')),
            dateTo: CarbonImmutable::parse((string) ($data['date_to'] ?? 'now')),
            imageCdnUrl: isset($data['image_cdn_url']) ? (string) $data['image_cdn_url'] : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'year' => $this->year,
            'language' => $this->language,
            'age_group' => $this->ageGroup,
            'title' => $this->title,
            'number' => $this->number,
            'date_from' => $this->dateFrom->toDateString(),
            'date_to' => $this->dateTo->toDateString(),
            'image_cdn_url' => $this->imageCdnUrl,
        ];
    }
}

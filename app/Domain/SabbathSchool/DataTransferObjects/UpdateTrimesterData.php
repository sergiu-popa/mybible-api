<?php

declare(strict_types=1);

namespace App\Domain\SabbathSchool\DataTransferObjects;

use Carbon\CarbonImmutable;

/**
 * Partial DTO for PATCH /admin/.../trimesters/{trimester}. Only fields
 * that were present in the validated payload are emitted by `toArray()`,
 * so an unset field is preserved on the model rather than nulled.
 */
final readonly class UpdateTrimesterData
{
    /**
     * @param  array<int, string>  $present  validated keys actually supplied
     */
    public function __construct(
        public ?string $year,
        public ?string $language,
        public ?string $ageGroup,
        public ?string $title,
        public ?int $number,
        public ?CarbonImmutable $dateFrom,
        public ?CarbonImmutable $dateTo,
        public ?string $imageCdnUrl,
        public array $present,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function from(array $data): self
    {
        return new self(
            year: array_key_exists('year', $data) ? (string) $data['year'] : null,
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
            imageCdnUrl: array_key_exists('image_cdn_url', $data) && $data['image_cdn_url'] !== null
                ? (string) $data['image_cdn_url']
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
            'year' => $this->year,
            'language' => $this->language,
            'age_group' => $this->ageGroup,
            'title' => $this->title,
            'number' => $this->number,
            'date_from' => $this->dateFrom?->toDateString(),
            'date_to' => $this->dateTo?->toDateString(),
            'image_cdn_url' => $this->imageCdnUrl,
        ];

        return array_intersect_key($map, array_flip($this->present));
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Commentary\DataTransferObjects;

final readonly class SubmitCommentaryErrorReportData
{
    public function __construct(
        public int $commentaryTextId,
        public string $description,
        public ?int $verse = null,
        public ?string $deviceId = null,
        public ?int $userId = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function from(array $data, int $commentaryTextId, ?int $userId = null): self
    {
        return new self(
            commentaryTextId: $commentaryTextId,
            description: (string) ($data['description'] ?? ''),
            verse: isset($data['verse']) ? (int) $data['verse'] : null,
            deviceId: isset($data['device_id']) ? (string) $data['device_id'] : null,
            userId: $userId,
        );
    }
}

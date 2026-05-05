<?php

declare(strict_types=1);

namespace App\Domain\Commentary\DataTransferObjects;

use App\Domain\Commentary\Enums\CommentaryErrorReportStatus;

final readonly class UpdateCommentaryErrorReportData
{
    public function __construct(
        public CommentaryErrorReportStatus $status,
        public ?int $reviewedByUserId,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function from(array $data, ?int $reviewedByUserId): self
    {
        return new self(
            status: CommentaryErrorReportStatus::from((string) ($data['status'] ?? 'pending')),
            reviewedByUserId: $reviewedByUserId,
        );
    }
}

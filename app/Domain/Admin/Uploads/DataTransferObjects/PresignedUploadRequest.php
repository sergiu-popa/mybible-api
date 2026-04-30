<?php

declare(strict_types=1);

namespace App\Domain\Admin\Uploads\DataTransferObjects;

final readonly class PresignedUploadRequest
{
    public function __construct(
        public string $filename,
        public string $contentType,
        public int $sizeBytes,
    ) {}

    /**
     * @param  array{filename: string, content_type: string, size: int}  $payload
     */
    public static function from(array $payload): self
    {
        return new self(
            filename: $payload['filename'],
            contentType: $payload['content_type'],
            sizeBytes: $payload['size'],
        );
    }
}

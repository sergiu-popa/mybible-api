<?php

declare(strict_types=1);

namespace App\Domain\QrCode\DataTransferObjects;

final readonly class CreateQrCodeData
{
    public function __construct(
        public string $place,
        public ?string $baseUrl,
        public string $source,
        public string $destination,
        public string $name,
        public string $content,
        public ?string $description,
        public ?string $reference,
        public ?string $imagePath,
    ) {}
}

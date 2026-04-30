<?php

declare(strict_types=1);

namespace App\Domain\Admin\Uploads\DataTransferObjects;

use Illuminate\Support\Carbon;

final readonly class PresignedUploadResult
{
    /**
     * @param  array<string, string>  $headers
     */
    public function __construct(
        public string $key,
        public string $uploadUrl,
        public Carbon $expiresAt,
        public array $headers,
    ) {}
}

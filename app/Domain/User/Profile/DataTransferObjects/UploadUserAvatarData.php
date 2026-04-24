<?php

declare(strict_types=1);

namespace App\Domain\User\Profile\DataTransferObjects;

use Illuminate\Http\UploadedFile;

final readonly class UploadUserAvatarData
{
    public function __construct(
        public UploadedFile $file,
    ) {}

    /**
     * @param  array{avatar: UploadedFile}  $payload
     */
    public static function from(array $payload): self
    {
        return new self(
            file: $payload['avatar'],
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\User\Profile\DataTransferObjects;

final readonly class ChangeUserPasswordData
{
    public function __construct(
        #[\SensitiveParameter] public string $currentPassword,
        #[\SensitiveParameter] public string $newPassword,
    ) {}

    /**
     * @param  array{current_password: string, new_password: string}  $payload
     */
    public static function from(array $payload): self
    {
        return new self(
            currentPassword: $payload['current_password'],
            newPassword: $payload['new_password'],
        );
    }
}

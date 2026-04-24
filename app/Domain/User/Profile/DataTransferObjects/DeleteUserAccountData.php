<?php

declare(strict_types=1);

namespace App\Domain\User\Profile\DataTransferObjects;

final readonly class DeleteUserAccountData
{
    public function __construct(
        #[\SensitiveParameter] public string $password,
    ) {}

    /**
     * @param  array{password: string}  $payload
     */
    public static function from(array $payload): self
    {
        return new self(
            password: $payload['password'],
        );
    }
}

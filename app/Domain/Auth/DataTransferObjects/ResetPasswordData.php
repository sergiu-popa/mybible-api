<?php

declare(strict_types=1);

namespace App\Domain\Auth\DataTransferObjects;

final readonly class ResetPasswordData
{
    public function __construct(
        public string $email,
        public string $token,
        #[\SensitiveParameter]
        public string $password,
    ) {}

    /**
     * @param  array{email: string, token: string, password: string}  $payload
     */
    public static function from(array $payload): self
    {
        return new self(
            email: $payload['email'],
            token: $payload['token'],
            password: $payload['password'],
        );
    }
}

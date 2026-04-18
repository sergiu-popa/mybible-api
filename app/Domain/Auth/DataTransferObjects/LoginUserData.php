<?php

declare(strict_types=1);

namespace App\Domain\Auth\DataTransferObjects;

final readonly class LoginUserData
{
    public function __construct(
        public string $email,
        public string $password,
    ) {}

    /**
     * @param  array{email: string, password: string}  $payload
     */
    public static function from(array $payload): self
    {
        return new self(
            email: $payload['email'],
            password: $payload['password'],
        );
    }
}

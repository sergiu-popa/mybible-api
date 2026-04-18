<?php

declare(strict_types=1);

namespace App\Domain\Auth\DataTransferObjects;

final readonly class RegisterUserData
{
    public function __construct(
        public string $name,
        public string $email,
        public string $password,
    ) {}

    /**
     * @param  array{name: string, email: string, password: string}  $payload
     */
    public static function from(array $payload): self
    {
        return new self(
            name: $payload['name'],
            email: $payload['email'],
            password: $payload['password'],
        );
    }
}

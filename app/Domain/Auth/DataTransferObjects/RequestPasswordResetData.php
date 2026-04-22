<?php

declare(strict_types=1);

namespace App\Domain\Auth\DataTransferObjects;

final readonly class RequestPasswordResetData
{
    public function __construct(
        public string $email,
    ) {}

    /**
     * @param  array{email: string}  $payload
     */
    public static function from(array $payload): self
    {
        return new self(
            email: $payload['email'],
        );
    }
}

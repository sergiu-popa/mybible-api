<?php

declare(strict_types=1);

namespace App\Domain\Auth\DataTransferObjects;

use App\Models\User;

final readonly class AuthTokenData
{
    public function __construct(
        public User $user,
        public string $plainTextToken,
    ) {}
}

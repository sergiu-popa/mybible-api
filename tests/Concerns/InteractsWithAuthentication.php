<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Models\User;

trait InteractsWithAuthentication
{
    protected function givenAnAuthenticatedUser(?User $user = null): User
    {
        $user ??= User::factory()->create();

        $token = $user->createToken('test')->plainTextToken;
        $this->withHeader('Authorization', 'Bearer ' . $token);

        return $user;
    }
}

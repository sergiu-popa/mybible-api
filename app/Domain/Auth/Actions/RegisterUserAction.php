<?php

declare(strict_types=1);

namespace App\Domain\Auth\Actions;

use App\Domain\Auth\DataTransferObjects\AuthTokenData;
use App\Domain\Auth\DataTransferObjects\RegisterUserData;
use App\Models\User;

final class RegisterUserAction
{
    public function execute(RegisterUserData $data): AuthTokenData
    {
        $user = User::create([
            'name' => $data->name,
            'email' => $data->email,
            'password' => $data->password,
            'roles' => [],
        ]);

        $token = $user->createToken('auth')->plainTextToken;

        return new AuthTokenData(
            user: $user,
            plainTextToken: $token,
        );
    }
}

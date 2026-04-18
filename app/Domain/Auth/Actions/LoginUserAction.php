<?php

declare(strict_types=1);

namespace App\Domain\Auth\Actions;

use App\Domain\Auth\DataTransferObjects\AuthTokenData;
use App\Domain\Auth\DataTransferObjects\LoginUserData;
use App\Domain\Auth\Exceptions\InvalidCredentialsException;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

final class LoginUserAction
{
    /**
     * A pre-hashed dummy value used to burn CPU when the user does not exist,
     * so a failed lookup is not trivially distinguishable from a wrong
     * password via wall-clock timing.
     */
    private const DUMMY_HASH = '$2y$12$EoB0YDn5a5mBx2bynAImmukkrJXpAwDw6jvW5P6sorYRaWA7B29Ja';

    public function execute(LoginUserData $data): AuthTokenData
    {
        $user = User::where('email', $data->email)->first();

        if ($user === null) {
            Hash::check($data->password, self::DUMMY_HASH);

            throw new InvalidCredentialsException;
        }

        if (! Hash::check($data->password, $user->password)) {
            throw new InvalidCredentialsException;
        }

        $token = $user->createToken('auth')->plainTextToken;

        return new AuthTokenData(
            user: $user,
            plainTextToken: $token,
        );
    }
}

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
     * Lazily-computed dummy hash used to burn CPU when the user does not exist,
     * so a failed lookup is not trivially distinguishable from a wrong
     * password via wall-clock timing. Computed via the configured hasher so the
     * cost tracks `config('hashing.bcrypt.rounds')` instead of a frozen literal.
     */
    private static ?string $dummyHash = null;

    public function execute(LoginUserData $data): AuthTokenData
    {
        $user = User::where('email', $data->email)->first();

        if ($user === null) {
            Hash::check($data->password, self::dummyHash());

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

    private static function dummyHash(): string
    {
        if (self::$dummyHash === null) {
            self::$dummyHash = Hash::make('dummy-password');
        }

        return self::$dummyHash;
    }
}

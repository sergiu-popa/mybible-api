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

        $driver = self::driverForHash($user->password);

        if ($driver === null || ! Hash::driver($driver)->check($data->password, $user->password)) {
            throw new InvalidCredentialsException;
        }

        if ($driver !== 'argon2id') {
            // Symfony stored some passwords as bcrypt under its `auto` hasher.
            // Re-hash to the configured Argon2id driver on first successful
            // login so legacy rows converge without forcing a password reset.
            $user->password = $data->password;
            $user->save();
        }

        $token = $user->createToken('auth')->plainTextToken;

        return new AuthTokenData(
            user: $user,
            plainTextToken: $token,
        );
    }

    private static function driverForHash(string $hash): ?string
    {
        return match (Hash::info($hash)['algoName'] ?? null) {
            'argon2id' => 'argon2id',
            'argon2i' => 'argon',
            'bcrypt' => 'bcrypt',
            default => null,
        };
    }

    private static function dummyHash(): string
    {
        if (self::$dummyHash === null) {
            self::$dummyHash = Hash::make('dummy-password');
        }

        return self::$dummyHash;
    }
}

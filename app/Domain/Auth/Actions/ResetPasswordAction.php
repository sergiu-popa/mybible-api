<?php

declare(strict_types=1);

namespace App\Domain\Auth\Actions;

use App\Domain\Auth\DataTransferObjects\ResetPasswordData;
use App\Domain\Auth\Exceptions\InvalidPasswordResetTokenException;
use App\Models\User;
use Illuminate\Support\Facades\Password;

final class ResetPasswordAction
{
    public function execute(ResetPasswordData $data): void
    {
        $status = Password::broker()->reset(
            [
                'email' => $data->email,
                'token' => $data->token,
                'password' => $data->password,
                'password_confirmation' => $data->password,
            ],
            function (User $user, string $plain): void {
                // The `hashed` cast on the model takes care of hashing the
                // plaintext under the configured Argon2id driver.
                $user->password = $plain;
                $user->save();
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw new InvalidPasswordResetTokenException;
        }
    }
}

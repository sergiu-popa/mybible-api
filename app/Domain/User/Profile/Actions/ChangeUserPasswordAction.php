<?php

declare(strict_types=1);

namespace App\Domain\User\Profile\Actions;

use App\Domain\User\Profile\DataTransferObjects\ChangeUserPasswordData;
use App\Domain\User\Profile\Exceptions\IncorrectCurrentPasswordException;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;

final class ChangeUserPasswordAction
{
    public function execute(
        User $user,
        ChangeUserPasswordData $data,
        PersonalAccessToken $currentToken,
    ): void {
        if (! Hash::check($data->currentPassword, $user->password)) {
            throw IncorrectCurrentPasswordException::forField('current_password');
        }

        // The `hashed` cast on the User model hashes the plaintext under the
        // configured Argon2id hasher.
        $user->password = $data->newPassword;
        $user->save();

        // Revoke every other Sanctum token for this user. The current token
        // is retained so the client issuing the password change is not
        // forced to re-authenticate in the same round trip.
        $user->tokens()
            ->where('id', '!=', $currentToken->getKey())
            ->delete();
    }
}

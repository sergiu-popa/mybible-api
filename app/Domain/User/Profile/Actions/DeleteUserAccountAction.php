<?php

declare(strict_types=1);

namespace App\Domain\User\Profile\Actions;

use App\Domain\User\Profile\DataTransferObjects\DeleteUserAccountData;
use App\Domain\User\Profile\Events\UserAccountDeleted;
use App\Domain\User\Profile\Exceptions\IncorrectCurrentPasswordException;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

final class DeleteUserAccountAction
{
    public function execute(User $user, DeleteUserAccountData $data): void
    {
        if (! Hash::check($data->password, $user->password)) {
            throw IncorrectCurrentPasswordException::forField('password');
        }

        // Revoke all Sanctum tokens — the account is gone.
        $user->tokens()->delete();

        // Fire the event before the soft-delete so any listener that reloads
        // the user still resolves a live row.
        UserAccountDeleted::dispatch($user->getKey(), $user->email);

        $user->delete();
    }
}

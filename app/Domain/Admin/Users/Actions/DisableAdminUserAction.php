<?php

declare(strict_types=1);

namespace App\Domain\Admin\Users\Actions;

use App\Models\User;

final class DisableAdminUserAction
{
    /**
     * Flip `is_active` to false and revoke every active Sanctum token so
     * the disabled admin is logged out of all sessions immediately.
     */
    public function execute(User $user): User
    {
        $user->is_active = false;
        $user->save();

        $user->tokens()->delete();

        return $user->refresh();
    }
}

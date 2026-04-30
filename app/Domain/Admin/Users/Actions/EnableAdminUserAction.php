<?php

declare(strict_types=1);

namespace App\Domain\Admin\Users\Actions;

use App\Models\User;

final class EnableAdminUserAction
{
    public function execute(User $user): User
    {
        $user->is_active = true;
        $user->save();

        return $user->refresh();
    }
}

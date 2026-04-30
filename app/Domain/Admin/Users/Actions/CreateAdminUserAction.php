<?php

declare(strict_types=1);

namespace App\Domain\Admin\Users\Actions;

use App\Domain\Admin\Users\DataTransferObjects\CreateAdminUserData;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

final class CreateAdminUserAction
{
    /**
     * Create a new admin account. The password is seeded with a random
     * value the user can never recover; setting a real password happens
     * through the password-reset flow (`SendAdminPasswordResetAction`).
     */
    public function execute(CreateAdminUserData $data): User
    {
        return User::create([
            'name' => $data->name,
            'email' => $data->email,
            'password' => Hash::make(Str::random(40)),
            'roles' => ['admin'],
            'is_super' => $data->isSuper,
            'languages' => $data->languages,
            'ui_locale' => $data->uiLocale,
            'is_active' => true,
        ]);
    }
}

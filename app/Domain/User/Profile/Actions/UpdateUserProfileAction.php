<?php

declare(strict_types=1);

namespace App\Domain\User\Profile\Actions;

use App\Domain\User\Profile\DataTransferObjects\UpdateUserProfileData;
use App\Models\User;

final class UpdateUserProfileAction
{
    public function execute(User $user, UpdateUserProfileData $data): User
    {
        if ($data->name !== null) {
            $user->name = $data->name;
        }

        if ($data->language !== null) {
            $user->language = $data->language->value;
        }

        if ($data->preferredVersion !== null) {
            $user->preferred_version = $data->preferredVersion;
        }

        $user->save();

        return $user->refresh();
    }
}

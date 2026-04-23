<?php

declare(strict_types=1);

namespace App\Domain\User\Profile\Actions;

use App\Models\User;
use Illuminate\Support\Facades\Storage;

final class RemoveUserAvatarAction
{
    private const string DISK = 'avatars';

    public function execute(User $user): User
    {
        $path = $user->avatar;

        if (is_string($path) && $path !== '') {
            $disk = Storage::disk(self::DISK);

            if ($disk->exists($path)) {
                $disk->delete($path);
            }
        }

        $user->avatar = null;
        $user->save();

        return $user->refresh();
    }
}

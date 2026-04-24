<?php

declare(strict_types=1);

namespace App\Domain\User\Profile\Actions;

use App\Domain\User\Profile\DataTransferObjects\UploadUserAvatarData;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class UploadUserAvatarAction
{
    private const string DISK = 'avatars';

    public function execute(User $user, UploadUserAvatarData $data): User
    {
        $disk = Storage::disk(self::DISK);

        $extension = $data->file->extension();
        if ($extension === '') {
            $extension = $data->file->getClientOriginalExtension();
        }

        $relativePath = sprintf('%d/%s.%s', $user->getKey(), (string) Str::ulid(), $extension);

        $disk->putFileAs('', $data->file, $relativePath);

        // Remove the previous avatar if the column was populated and the
        // underlying file still exists on the disk.
        $previous = $user->avatar;
        if (is_string($previous) && $previous !== '' && $disk->exists($previous)) {
            $disk->delete($previous);
        }

        $user->avatar = $relativePath;
        $user->save();

        return $user->refresh();
    }
}

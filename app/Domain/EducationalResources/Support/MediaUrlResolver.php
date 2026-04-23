<?php

declare(strict_types=1);

namespace App\Domain\EducationalResources\Support;

use Illuminate\Support\Facades\Storage;

final class MediaUrlResolver
{
    public static function absoluteUrl(?string $path, string $disk): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        return Storage::disk($disk)->url($path);
    }
}

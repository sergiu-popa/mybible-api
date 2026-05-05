<?php

declare(strict_types=1);

namespace App\Domain\Commentary\Support;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

/**
 * Computes the next revision tag (`v1`, `v2`, …) for a given commentary
 * slug by listing the S3 prefix `commentaries/{slug}/`. Race-resilient
 * because callers wrap the export in a `Cache::lock("sqlite-export:{slug}")`.
 */
final class CommentarySqliteRevisionResolver
{
    public function __construct(
        private readonly ?Filesystem $disk = null,
    ) {}

    public function next(string $slug): int
    {
        $disk = $this->disk ?? Storage::disk((string) config('filesystems.default'));
        $prefix = sprintf('commentaries/%s', $slug);

        $files = $disk->files($prefix);

        $maxRevision = 0;
        foreach ($files as $file) {
            $basename = basename((string) $file);
            if (preg_match('/^v(\d+)\.sqlite$/', $basename, $matches) === 1) {
                $maxRevision = max($maxRevision, (int) $matches[1]);
            }
        }

        return $maxRevision + 1;
    }
}

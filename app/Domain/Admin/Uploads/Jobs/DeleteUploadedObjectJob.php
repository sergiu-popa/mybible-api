<?php

declare(strict_types=1);

namespace App\Domain\Admin\Uploads\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Deletes an object from a configured filesystem disk. Used to clean up
 * S3 objects backing deleted admin-managed entities (educational
 * resources, news hero images) so we do not leak storage.
 *
 * Errors are logged and swallowed: the job is a best-effort cleanup
 * that must not fail the parent transaction it was queued from.
 */
final class DeleteUploadedObjectJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly string $disk,
        public readonly string $path,
    ) {
        $this->onQueue('cleanup');
    }

    public function handle(): void
    {
        try {
            Storage::disk($this->disk)->delete($this->path);
        } catch (Throwable $exception) {
            Log::warning('Failed to delete uploaded object from disk.', [
                'disk' => $this->disk,
                'path' => $this->path,
                'exception' => $exception->getMessage(),
            ]);
        }
    }
}

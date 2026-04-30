<?php

declare(strict_types=1);

namespace App\Domain\Admin\Uploads\Actions;

use App\Domain\Admin\Uploads\DataTransferObjects\PresignedUploadRequest;
use App\Domain\Admin\Uploads\DataTransferObjects\PresignedUploadResult;
use Illuminate\Filesystem\AwsS3V3Adapter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Not `final` so test doubles can extend it without reaching for Mockery
 * tricks; behaviour subclasses outside of tests are not expected.
 */
class IssuePresignedUploadAction
{
    private const KEY_PREFIX = 'admin-uploads';

    private const TTL_MINUTES = 10;

    /**
     * Issues a one-shot S3 presigned PUT URL the admin can stream the
     * object to directly, bypassing the API process. Each upload lives
     * under `admin-uploads/{uuid}/{slug-of-filename}` so collisions are
     * impossible and downstream "finalize" calls (per-capability create
     * endpoints) can carry the returned `key` opaquely.
     */
    public function execute(PresignedUploadRequest $request): PresignedUploadResult
    {
        $disk = Storage::disk('s3');

        if (! $disk instanceof AwsS3V3Adapter) {
            throw new RuntimeException(
                'S3 disk is not configured; presigned upload URLs require an S3 driver.',
            );
        }

        $key = $this->buildKey($request->filename);
        $expiresAt = Carbon::now()->addMinutes(self::TTL_MINUTES);

        $headers = [
            'Content-Type' => $request->contentType,
            'Content-Length' => (string) $request->sizeBytes,
        ];

        $uploadUrl = $disk->temporaryUploadUrl($key, $expiresAt, $headers)['url'];

        return new PresignedUploadResult(
            key: $key,
            uploadUrl: $uploadUrl,
            expiresAt: $expiresAt,
            headers: $headers,
        );
    }

    private function buildKey(string $filename): string
    {
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $base = pathinfo($filename, PATHINFO_FILENAME);
        $slug = Str::slug($base) ?: 'file';
        $suffix = $extension !== '' ? '.' . strtolower($extension) : '';

        return sprintf('%s/%s/%s%s', self::KEY_PREFIX, (string) Str::uuid(), $slug, $suffix);
    }
}

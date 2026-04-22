<?php

declare(strict_types=1);

namespace App\Domain\Bible\Support;

use App\Domain\Bible\Models\BibleVersion;
use Illuminate\Database\Eloquent\Builder;

final class BibleCacheHeaders
{
    public const LIST_MAX_AGE = 3600;

    public const EXPORT_MAX_AGE = 86400;

    /**
     * Headers for a list endpoint. The ETag is computed from the max
     * `updated_at` and row count across the filtered set so pagination
     * cannot shift it.
     *
     * @param  Builder<BibleVersion>  $query
     * @return array{Cache-Control: string, ETag: string}
     */
    public static function forVersionList(Builder $query): array
    {
        $row = (clone $query)
            ->toBase()
            ->reorder()
            ->selectRaw('MAX(updated_at) as max_updated_at, COUNT(*) as total')
            ->first();

        $max = $row?->max_updated_at;
        $count = $row === null ? 0 : (int) $row->total;

        $payload = 'versions|' . ($max ?? '') . '|' . $count;

        return [
            'Cache-Control' => 'public, max-age=' . self::LIST_MAX_AGE,
            'ETag' => self::strongEtag($payload),
        ];
    }

    /**
     * @return array{Cache-Control: string, ETag: string}
     */
    public static function forVersionExport(BibleVersion $version): array
    {
        $timestamp = $version->updated_at->toIso8601String();

        return [
            'Cache-Control' => 'public, max-age=' . self::EXPORT_MAX_AGE,
            'ETag' => self::strongEtag('export|version:' . $version->id . '|' . $timestamp),
        ];
    }

    private static function strongEtag(string $payload): string
    {
        return '"' . sha1($payload) . '"';
    }
}

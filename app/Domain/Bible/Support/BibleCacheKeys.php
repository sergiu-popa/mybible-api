<?php

declare(strict_types=1);

namespace App\Domain\Bible\Support;

use App\Domain\Shared\Enums\Language;

final class BibleCacheKeys
{
    public const TAG_ROOT = 'bible';

    public const TAG_VERSIONS = 'bible:versions';

    public static function versionsList(?Language $language, int $page, int $perPage): string
    {
        $lang = $language === null ? 'all' : $language->value;

        return sprintf('bible:versions:list:%s:p%d:%d', $lang, $page, $perPage);
    }

    public static function versionExport(string $abbreviation): string
    {
        return sprintf('bible:export:%s', $abbreviation);
    }

    /**
     * @return array<int, string>
     */
    public static function tagsForVersionList(): array
    {
        return [self::TAG_ROOT, self::TAG_VERSIONS];
    }

    /**
     * @return array<int, string>
     */
    public static function tagsForExport(string $abbreviation): array
    {
        return [self::TAG_ROOT, sprintf('bible:export:%s', $abbreviation)];
    }
}

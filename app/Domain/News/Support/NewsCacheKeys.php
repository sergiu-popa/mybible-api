<?php

declare(strict_types=1);

namespace App\Domain\News\Support;

use App\Domain\Shared\Enums\Language;

final class NewsCacheKeys
{
    public const TAG_ROOT = 'news';

    public static function list(Language $language, int $page, int $perPage): string
    {
        return sprintf('news:%s:p%d:%d', $language->value, $page, $perPage);
    }

    /**
     * @return array<int, string>
     */
    public static function tagsForNews(): array
    {
        return [self::TAG_ROOT];
    }
}

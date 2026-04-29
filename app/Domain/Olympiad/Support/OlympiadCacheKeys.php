<?php

declare(strict_types=1);

namespace App\Domain\Olympiad\Support;

use App\Domain\Reference\ChapterRange;
use App\Domain\Shared\Enums\Language;

final class OlympiadCacheKeys
{
    public const TAG_ROOT = 'oly';

    public static function themesList(Language $language, int $page, int $perPage): string
    {
        return sprintf('oly:themes:%s:p%d:%d', $language->value, $page, $perPage);
    }

    public static function themeQuestions(string $book, ChapterRange $range, Language $language): string
    {
        return sprintf(
            'oly:theme:%s:%d-%d:%s',
            $book,
            $range->from,
            $range->to,
            $language->value,
        );
    }

    /**
     * @return array<int, string>
     */
    public static function tagsForThemesList(): array
    {
        return [self::TAG_ROOT];
    }

    /**
     * @return array<int, string>
     */
    public static function tagsForTheme(string $book, ChapterRange $range, Language $language): array
    {
        return [
            self::TAG_ROOT,
            sprintf('oly:theme:%s:%d-%d:%s', $book, $range->from, $range->to, $language->value),
        ];
    }
}

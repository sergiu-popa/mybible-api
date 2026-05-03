<?php

declare(strict_types=1);

namespace App\Domain\SabbathSchool\Support;

use App\Domain\Shared\Enums\Language;

/**
 * Single source of truth for Sabbath School cache keys + tags. Read paths
 * fetch keys, write paths fetch tags via the matching `tagsFor*()`
 * helpers — never hard-coded in a controller or Action.
 */
final class SabbathSchoolCacheKeys
{
    public const TAG_ROOT = 'ss';

    public const TAG_LESSONS_LIST = 'ss:lessons';

    public const TAG_TRIMESTERS_LIST = 'ss:trimesters';

    public static function lessonsList(
        Language $language,
        int $page,
        int $perPage,
        ?int $trimesterId = null,
        ?string $ageGroup = null,
    ): string {
        return sprintf(
            'ss:lessons:list:%s:t%s:a%s:p%d:%d',
            $language->value,
            $trimesterId === null ? 'all' : (string) $trimesterId,
            $ageGroup ?? 'all',
            $page,
            $perPage,
        );
    }

    public static function lesson(int $lessonId, Language $language): string
    {
        return sprintf('ss:lesson:%d:%s', $lessonId, $language->value);
    }

    public static function trimestersList(Language $language): string
    {
        return sprintf('ss:trimesters:list:%s', $language->value);
    }

    public static function trimester(int $trimesterId, Language $language): string
    {
        return sprintf('ss:trimester:%d:%s', $trimesterId, $language->value);
    }

    /**
     * @return array<int, string>
     */
    public static function tagsForLessonsList(): array
    {
        return [self::TAG_ROOT, self::TAG_LESSONS_LIST, self::TAG_TRIMESTERS_LIST];
    }

    /**
     * @return array<int, string>
     */
    public static function tagsForLesson(int $lessonId): array
    {
        return [self::TAG_ROOT, sprintf('ss:lesson:%d', $lessonId)];
    }

    /**
     * @return array<int, string>
     */
    public static function tagsForTrimestersList(): array
    {
        return [self::TAG_ROOT, self::TAG_TRIMESTERS_LIST];
    }

    /**
     * @return array<int, string>
     */
    public static function tagsForTrimester(int $trimesterId): array
    {
        return [self::TAG_ROOT, sprintf('ss:trimester:%d', $trimesterId)];
    }
}

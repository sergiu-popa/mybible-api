<?php

declare(strict_types=1);

namespace App\Domain\SabbathSchool\Support;

use App\Domain\Shared\Enums\Language;

/**
 * Single source of truth for Sabbath School cache keys + tags. Read paths
 * fetch keys, future write paths fetch tags via the matching `tagsFor*()`
 * helpers — never hard-coded in a controller or Action.
 */
final class SabbathSchoolCacheKeys
{
    public const TAG_ROOT = 'ss';

    public const TAG_LESSONS_LIST = 'ss:lessons';

    public static function lessonsList(Language $language, int $page, int $perPage): string
    {
        return sprintf('ss:lessons:list:%s:p%d:%d', $language->value, $page, $perPage);
    }

    public static function lesson(int $lessonId, Language $language): string
    {
        return sprintf('ss:lesson:%d:%s', $lessonId, $language->value);
    }

    /**
     * @return array<int, string>
     */
    public static function tagsForLessonsList(): array
    {
        return [self::TAG_ROOT, self::TAG_LESSONS_LIST];
    }

    /**
     * @return array<int, string>
     */
    public static function tagsForLesson(int $lessonId): array
    {
        return [self::TAG_ROOT, sprintf('ss:lesson:%d', $lessonId)];
    }
}

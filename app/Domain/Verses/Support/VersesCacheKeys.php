<?php

declare(strict_types=1);

namespace App\Domain\Verses\Support;

use DateTimeInterface;

final class VersesCacheKeys
{
    public const TAG_DAILY_VERSE = 'daily-verse';

    public const TAG_ROOT = 'verses';

    public static function dailyVerse(DateTimeInterface $date): string
    {
        return sprintf('verses:daily:%s', $date->format('Y-m-d'));
    }

    /**
     * @return array<int, string>
     */
    public static function tagsForDailyVerse(): array
    {
        return [self::TAG_ROOT, self::TAG_DAILY_VERSE];
    }
}

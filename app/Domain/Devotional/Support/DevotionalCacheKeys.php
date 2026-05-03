<?php

declare(strict_types=1);

namespace App\Domain\Devotional\Support;

use App\Domain\Shared\Enums\Language;
use Carbon\CarbonInterface;

final class DevotionalCacheKeys
{
    public const TAG_ROOT = 'dev';

    public static function show(Language $language, int $typeId, CarbonInterface $date): string
    {
        return sprintf(
            'dev:%s:%d:%s',
            $language->value,
            $typeId,
            $date->toDateString(),
        );
    }

    /**
     * @return array<int, string>
     */
    public static function tagsForDevotional(Language $language, int $typeId): array
    {
        return [self::TAG_ROOT, sprintf('dev:%s:%d', $language->value, $typeId)];
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Devotional\Support;

use App\Domain\Devotional\Enums\DevotionalType;
use App\Domain\Shared\Enums\Language;
use Carbon\CarbonInterface;

final class DevotionalCacheKeys
{
    public const TAG_ROOT = 'dev';

    public static function show(Language $language, DevotionalType $type, CarbonInterface $date): string
    {
        return sprintf(
            'dev:%s:%s:%s',
            $language->value,
            $type->value,
            $date->toDateString(),
        );
    }

    /**
     * @return array<int, string>
     */
    public static function tagsForDevotional(Language $language, DevotionalType $type): array
    {
        return [self::TAG_ROOT, sprintf('dev:%s:%s', $language->value, $type->value)];
    }
}

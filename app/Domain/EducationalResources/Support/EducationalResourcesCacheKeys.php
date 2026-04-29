<?php

declare(strict_types=1);

namespace App\Domain\EducationalResources\Support;

use App\Domain\EducationalResources\Enums\ResourceType;
use App\Domain\Shared\Enums\Language;

final class EducationalResourcesCacheKeys
{
    public const TAG_ROOT = 'edu';

    public static function categories(?Language $language, int $page, int $perPage): string
    {
        $lang = $language === null ? 'all' : $language->value;

        return sprintf('edu:categories:%s:p%d:%d', $lang, $page, $perPage);
    }

    public static function resourcesByCategory(int $categoryId, int $page, int $perPage, ?ResourceType $type): string
    {
        return sprintf(
            'edu:cat:%d:p%d:%d:%s',
            $categoryId,
            $page,
            $perPage,
            $type === null ? 'all' : $type->value,
        );
    }

    /**
     * @return array<int, string>
     */
    public static function tagsForCategoriesList(): array
    {
        return [self::TAG_ROOT];
    }

    /**
     * @return array<int, string>
     */
    public static function tagsForCategory(int $categoryId): array
    {
        return [self::TAG_ROOT, sprintf('edu:cat:%d', $categoryId)];
    }
}

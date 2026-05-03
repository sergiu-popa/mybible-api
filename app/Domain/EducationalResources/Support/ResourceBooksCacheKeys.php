<?php

declare(strict_types=1);

namespace App\Domain\EducationalResources\Support;

use App\Domain\Shared\Enums\Language;

final class ResourceBooksCacheKeys
{
    public const TAG_ROOT = 'resource_books';

    public static function list(?Language $language, int $page, int $perPage): string
    {
        $lang = $language === null ? 'all' : $language->value;

        return sprintf('resource_books:list:%s:p%d:%d', $lang, $page, $perPage);
    }

    public static function detail(string $slug): string
    {
        return sprintf('resource_books:detail:%s', $slug);
    }

    public static function chapter(string $bookSlug, int $chapterId): string
    {
        return sprintf('resource_books:detail:%s:chapter:%d', $bookSlug, $chapterId);
    }

    /**
     * @return array<int, string>
     */
    public static function tagsForList(): array
    {
        return [self::TAG_ROOT];
    }

    /**
     * @return array<int, string>
     */
    public static function tagsForBook(int $bookId): array
    {
        return [self::TAG_ROOT, sprintf('resource_books:%d', $bookId)];
    }
}

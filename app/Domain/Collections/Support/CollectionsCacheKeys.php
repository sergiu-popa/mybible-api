<?php

declare(strict_types=1);

namespace App\Domain\Collections\Support;

use App\Domain\Shared\Enums\Language;

final class CollectionsCacheKeys
{
    public const TAG_ROOT = 'col';

    public static function topicsList(Language $language, int $page, int $perPage): string
    {
        return sprintf('col:topics:%s:p%d:%d', $language->value, $page, $perPage);
    }

    public static function topic(int $topicId, Language $language): string
    {
        return sprintf('col:topic:%d:%s', $topicId, $language->value);
    }

    /**
     * @return array<int, string>
     */
    public static function tagsForTopicsList(): array
    {
        return [self::TAG_ROOT];
    }

    /**
     * @return array<int, string>
     */
    public static function tagsForTopic(int $topicId): array
    {
        return [self::TAG_ROOT, sprintf('col:topic:%d', $topicId)];
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Mobile\Support;

use App\Domain\Shared\Enums\Language;

final class MobileCacheKeys
{
    public static function bootstrap(Language $language): string
    {
        return sprintf('app:bootstrap:%s', $language->value);
    }

    /**
     * Union of all constituent cache tags so any constituent invalidation
     * (news publish, daily-verse upsert, SS lesson update, etc.) propagates
     * to the bootstrap key.
     *
     * @return array<int, string>
     */
    public static function tagsForBootstrap(): array
    {
        return ['app:bootstrap', 'news', 'daily-verse', 'dev', 'ss', 'ss:lessons', 'bible', 'bible:versions', 'qr', 'mobile-versions'];
    }
}

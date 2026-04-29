<?php

declare(strict_types=1);

namespace App\Domain\QrCode\Support;

final class QrCodeCacheKeys
{
    public const TAG_ROOT = 'qr';

    public static function show(string $canonicalReference): string
    {
        return sprintf('qr:%s', $canonicalReference);
    }

    /**
     * @return array<int, string>
     */
    public static function tagsForQr(): array
    {
        return [self::TAG_ROOT];
    }
}

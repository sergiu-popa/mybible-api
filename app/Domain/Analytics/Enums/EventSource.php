<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Enums;

enum EventSource: string
{
    case Ios = 'ios';
    case Android = 'android';
    case Web = 'web';

    public static function fromUserAgent(string $userAgent): ?self
    {
        if ($userAgent === '') {
            return null;
        }

        if (str_contains($userAgent, 'MyBibleMobile')) {
            if (str_contains($userAgent, 'ios')) {
                return self::Ios;
            }

            if (str_contains($userAgent, 'android')) {
                return self::Android;
            }

            return null;
        }

        if (preg_match('/(Mozilla|Chrome|Safari|Firefox|Edge)/i', $userAgent) === 1) {
            return self::Web;
        }

        return null;
    }
}

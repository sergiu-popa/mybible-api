<?php

declare(strict_types=1);

namespace App\Domain\Shared\Enums;

enum Language: string
{
    case En = 'en';
    case Ro = 'ro';
    case Hu = 'hu';

    public static function fromRequest(?string $value, self $fallback = self::En): self
    {
        if ($value === null) {
            return $fallback;
        }

        return self::tryFrom($value) ?? $fallback;
    }
}

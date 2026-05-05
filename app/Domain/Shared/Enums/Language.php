<?php

declare(strict_types=1);

namespace App\Domain\Shared\Enums;

enum Language: string
{
    case En = 'en';
    case Ro = 'ro';
    case Hu = 'hu';
    case Es = 'es';
    case Fr = 'fr';
    case De = 'de';
    case It = 'it';

    public static function fromRequest(?string $value, self $fallback = self::En): self
    {
        if ($value === null) {
            return $fallback;
        }

        return self::tryFrom($value) ?? $fallback;
    }
}

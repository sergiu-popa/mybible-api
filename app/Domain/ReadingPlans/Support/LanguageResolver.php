<?php

declare(strict_types=1);

namespace App\Domain\ReadingPlans\Support;

use App\Domain\Shared\Enums\Language;

final class LanguageResolver
{
    /**
     * @param  array<string, mixed>  $map
     */
    public static function resolve(array $map, Language $language, Language $fallback = Language::En): ?string
    {
        $value = $map[$language->value] ?? null;

        if (is_string($value) && $value !== '') {
            return $value;
        }

        $fallbackValue = $map[$fallback->value] ?? null;

        if (is_string($fallbackValue) && $fallbackValue !== '') {
            return $fallbackValue;
        }

        return null;
    }
}

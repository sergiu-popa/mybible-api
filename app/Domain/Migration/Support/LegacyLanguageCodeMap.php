<?php

declare(strict_types=1);

namespace App\Domain\Migration\Support;

/**
 * Maps Symfony's `varchar(3)` ISO-639-2 language codes to the Laravel
 * schema's `CHAR(2)` ISO-639-1 codes. Identity for codes that are
 * already 2-char keeps the action idempotent against a partially
 * backfilled column.
 *
 * `deu` and `ger` both resolve to `de` — `deu` is ISO-639-2/T (the
 * terminological code listed in MBA-023 AC §12) and `ger` is the
 * alternative bibliographic 639-2/B code; defensive entry covers data
 * sourced from Bibliographic-leaning vocabularies.
 */
final class LegacyLanguageCodeMap
{
    /** @var array<string, string> */
    public const MAP = [
        'ron' => 'ro',
        'eng' => 'en',
        'hun' => 'hu',
        'spa' => 'es',
        'fra' => 'fr',
        'deu' => 'de',
        'ger' => 'de',
        'ita' => 'it',
        'ro' => 'ro',
        'en' => 'en',
        'hu' => 'hu',
        'es' => 'es',
        'fr' => 'fr',
        'de' => 'de',
        'it' => 'it',
    ];

    public static function to2Char(string $legacy): ?string
    {
        $key = strtolower(trim($legacy));

        return self::MAP[$key] ?? null;
    }
}

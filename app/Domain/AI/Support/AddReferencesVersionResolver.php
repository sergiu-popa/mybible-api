<?php

declare(strict_types=1);

namespace App\Domain\AI\Support;

use App\Domain\Bible\Models\BibleVersion;
use App\Domain\LanguageSettings\Models\LanguageSetting;
use RuntimeException;

/**
 * Resolves the Bible version slug an AddReferences call should use.
 *
 * Resolution chain:
 *  1. Explicit `bibleVersionAbbreviation` from the input.
 *  2. `language_settings.default_bible_version` for the call's language.
 *  3. The `ai.default_bible_version_fallback` config value (default `VDC`).
 *
 * The resolved abbreviation must match a `bible_versions` row; otherwise
 * the helper throws so the caller fails fast on a misconfiguration rather
 * than emitting a broken `href` to the upstream model.
 */
final class AddReferencesVersionResolver
{
    public function resolve(string $language, ?string $explicitAbbreviation): string
    {
        $candidate = $this->pickCandidate($language, $explicitAbbreviation);

        if (! BibleVersion::query()->where('abbreviation', $candidate)->exists()) {
            throw new RuntimeException(sprintf(
                'Resolved Bible version "%s" does not match any bible_versions row.',
                $candidate,
            ));
        }

        return $candidate;
    }

    private function pickCandidate(string $language, ?string $explicitAbbreviation): string
    {
        if (is_string($explicitAbbreviation) && $explicitAbbreviation !== '') {
            return $explicitAbbreviation;
        }

        $setting = LanguageSetting::query()
            ->with('defaultBibleVersion')
            ->where('language', $language)
            ->first();

        $defaultAbbreviation = $setting?->defaultBibleVersion?->abbreviation;
        if (is_string($defaultAbbreviation) && $defaultAbbreviation !== '') {
            return $defaultAbbreviation;
        }

        return (string) config('ai.default_bible_version_fallback', 'VDC');
    }
}

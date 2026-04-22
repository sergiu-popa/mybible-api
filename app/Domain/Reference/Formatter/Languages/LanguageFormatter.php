<?php

declare(strict_types=1);

namespace App\Domain\Reference\Formatter\Languages;

use App\Domain\Reference\Exceptions\InvalidReferenceException;

interface LanguageFormatter
{
    /**
     * Localized book name for a canonical abbreviation (e.g. `GEN` → `Geneza`).
     */
    public function bookName(string $abbreviation): string;

    /**
     * Canonical abbreviation for a localized book name (e.g. `Geneza` → `GEN`).
     *
     * @throws InvalidReferenceException When the localized name is not recognised.
     */
    public function abbreviation(string $localized): string;

    /**
     * PCRE pattern matching free-text Bible references in this language.
     *
     * The pattern must expose two capture groups: the book name (group 1) and
     * the passage segment (group 2). Implementations must use the `i` flag so
     * that free-text matching is case-insensitive across languages.
     */
    public function linkifyRegex(): string;

    /**
     * Default Bible version code used by this language for canonical link
     * targets (e.g. `VDC` for Romanian).
     */
    public function defaultVersion(): string;
}

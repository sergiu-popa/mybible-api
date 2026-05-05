<?php

declare(strict_types=1);

namespace App\Domain\LanguageSettings\Actions;

use App\Domain\LanguageSettings\Models\LanguageSetting;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Returns the row for one language eager-loaded with the relations the
 * public resource exposes (default Bible version + commentary slugs).
 * The devotional-type relation is intentionally not loaded so the public
 * surface stays narrow even if the resource shape later forgets to skip
 * it.
 */
final class ShowPublicLanguageSettingAction
{
    public function execute(string $language): LanguageSetting
    {
        $setting = LanguageSetting::query()
            ->with(['defaultBibleVersion', 'defaultCommentary'])
            ->where('language', $language)
            ->first();

        if ($setting === null) {
            throw new ModelNotFoundException;
        }

        return $setting;
    }
}

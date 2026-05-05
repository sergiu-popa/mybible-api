<?php

declare(strict_types=1);

namespace App\Domain\LanguageSettings\Actions;

use App\Domain\LanguageSettings\Models\LanguageSetting;
use Illuminate\Database\Eloquent\Collection;

final class ListLanguageSettingsAction
{
    /**
     * @return Collection<int, LanguageSetting>
     */
    public function execute(): Collection
    {
        return LanguageSetting::query()
            ->with(['defaultBibleVersion', 'defaultCommentary', 'defaultDevotionalType'])
            ->orderBy('language')
            ->get();
    }
}

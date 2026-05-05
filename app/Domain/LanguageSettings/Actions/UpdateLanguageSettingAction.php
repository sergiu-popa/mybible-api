<?php

declare(strict_types=1);

namespace App\Domain\LanguageSettings\Actions;

use App\Domain\Bible\Models\BibleVersion;
use App\Domain\LanguageSettings\DataTransferObjects\UpdateLanguageSettingInput;
use App\Domain\LanguageSettings\Models\LanguageSetting;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final class UpdateLanguageSettingAction
{
    public function execute(LanguageSetting $setting, UpdateLanguageSettingInput $input): LanguageSetting
    {
        if ($input->bibleVersionProvided) {
            $setting->default_bible_version_id = $this->resolveBibleVersionId($input->defaultBibleVersionAbbreviation);
        }

        if ($input->commentaryProvided) {
            $setting->default_commentary_id = $input->defaultCommentaryId;
        }

        if ($input->devotionalTypeProvided) {
            $setting->default_devotional_type_id = $input->defaultDevotionalTypeId;
        }

        $setting->save();

        return $setting->fresh([
            'defaultBibleVersion',
            'defaultCommentary',
            'defaultDevotionalType',
        ]) ?? throw new ModelNotFoundException;
    }

    private function resolveBibleVersionId(?string $abbreviation): ?int
    {
        if ($abbreviation === null || $abbreviation === '') {
            return null;
        }

        $version = BibleVersion::query()->where('abbreviation', $abbreviation)->first();

        return $version?->id;
    }
}

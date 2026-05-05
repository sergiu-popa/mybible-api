<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\LanguageSettings;

use App\Domain\LanguageSettings\DataTransferObjects\UpdateLanguageSettingInput;
use App\Domain\LanguageSettings\Models\LanguageSetting;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateLanguageSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'default_bible_version_abbreviation' => [
                'sometimes',
                'nullable',
                'string',
                'exists:bible_versions,abbreviation',
            ],
            'default_commentary_id' => [
                'sometimes',
                'nullable',
                'integer',
                'exists:commentaries,id',
            ],
            'default_devotional_type_id' => [
                'sometimes',
                'nullable',
                'integer',
                'exists:devotional_types,id',
            ],
        ];
    }

    public function toData(LanguageSetting $setting): UpdateLanguageSettingInput
    {
        $input = $this->validated();

        return new UpdateLanguageSettingInput(
            language: $setting->language,
            bibleVersionProvided: array_key_exists('default_bible_version_abbreviation', $input),
            defaultBibleVersionAbbreviation: array_key_exists('default_bible_version_abbreviation', $input)
                && $input['default_bible_version_abbreviation'] !== null
                    ? (string) $input['default_bible_version_abbreviation']
                    : null,
            commentaryProvided: array_key_exists('default_commentary_id', $input),
            defaultCommentaryId: array_key_exists('default_commentary_id', $input)
                && $input['default_commentary_id'] !== null
                    ? (int) $input['default_commentary_id']
                    : null,
            devotionalTypeProvided: array_key_exists('default_devotional_type_id', $input),
            defaultDevotionalTypeId: array_key_exists('default_devotional_type_id', $input)
                && $input['default_devotional_type_id'] !== null
                    ? (int) $input['default_devotional_type_id']
                    : null,
        );
    }
}

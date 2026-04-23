<?php

declare(strict_types=1);

namespace App\Http\Requests\Profile;

use App\Domain\Shared\Enums\Language;
use App\Domain\User\Profile\DataTransferObjects\UpdateUserProfileData;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateUserProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'nullable', 'string', 'max:50'],
            'language' => ['sometimes', 'nullable', Rule::enum(Language::class)],
            'preferred_version' => [
                'sometimes',
                'nullable',
                'string',
                'max:16',
                Rule::exists('bible_versions', 'abbreviation'),
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $name = $this->input('name');
            $language = $this->input('language');
            $preferredVersion = $this->input('preferred_version');

            if ($name === null && $language === null && $preferredVersion === null) {
                $validator->errors()->add(
                    'name',
                    'At least one of name, language, or preferred_version is required.',
                );
            }
        });
    }

    public function toData(): UpdateUserProfileData
    {
        /** @var array{name?: string|null, language?: string|null, preferred_version?: string|null} $validated */
        $validated = $this->validated();

        return UpdateUserProfileData::from($validated);
    }
}

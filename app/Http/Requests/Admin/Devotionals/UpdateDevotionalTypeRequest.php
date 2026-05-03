<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Devotionals;

use App\Domain\Devotional\DataTransferObjects\UpdateDevotionalTypeData;
use App\Domain\Devotional\Models\DevotionalType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateDevotionalTypeRequest extends FormRequest
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
        $type = $this->route('type');
        $ignoreId = $type instanceof DevotionalType ? $type->id : null;
        $language = $this->has('language')
            ? $this->input('language')
            : ($type instanceof DevotionalType ? $type->language : null);

        return [
            'slug' => [
                'sometimes',
                'required',
                'string',
                'max:64',
                'regex:/^[a-z0-9-]+$/',
                Rule::unique('devotional_types', 'slug')
                    ->ignore($ignoreId)
                    ->where(fn ($query) => $query->where(
                        'language',
                        is_string($language) && $language !== '' ? $language : null,
                    )),
            ],
            'title' => ['sometimes', 'required', 'string', 'max:128'],
            'position' => ['sometimes', 'integer', 'min:0', 'max:65535'],
            'language' => ['sometimes', 'nullable', 'string', 'size:2'],
        ];
    }

    public function toData(): UpdateDevotionalTypeData
    {
        $input = $this->validated();

        return new UpdateDevotionalTypeData(
            slug: array_key_exists('slug', $input) ? (string) $input['slug'] : null,
            title: array_key_exists('title', $input) ? (string) $input['title'] : null,
            position: array_key_exists('position', $input) ? (int) $input['position'] : null,
            language: array_key_exists('language', $input) && $input['language'] !== null
                ? (string) $input['language']
                : null,
            slugProvided: array_key_exists('slug', $input),
            titleProvided: array_key_exists('title', $input),
            positionProvided: array_key_exists('position', $input),
            languageProvided: array_key_exists('language', $input),
        );
    }
}

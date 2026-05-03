<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Devotionals;

use App\Domain\Devotional\DataTransferObjects\CreateDevotionalTypeData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CreateDevotionalTypeRequest extends FormRequest
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
        $language = $this->input('language');

        return [
            'slug' => [
                'required',
                'string',
                'max:64',
                'regex:/^[a-z0-9-]+$/',
                Rule::unique('devotional_types', 'slug')->where(
                    fn ($query) => $query->where('language', is_string($language) && $language !== '' ? $language : null),
                ),
            ],
            'title' => ['required', 'string', 'max:128'],
            'position' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'language' => ['nullable', 'string', 'size:2'],
        ];
    }

    public function toData(): CreateDevotionalTypeData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return new CreateDevotionalTypeData(
            slug: (string) $validated['slug'],
            title: (string) $validated['title'],
            position: isset($validated['position']) ? (int) $validated['position'] : 0,
            language: isset($validated['language']) ? (string) $validated['language'] : null,
        );
    }
}

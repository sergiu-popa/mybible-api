<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\EducationalResources;

use App\Domain\EducationalResources\DataTransferObjects\ResourceBookData;
use App\Domain\Shared\Enums\Language;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CreateResourceBookRequest extends FormRequest
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
            'slug' => ['nullable', 'string', 'max:255', 'regex:/^[a-z0-9-]+$/', 'unique:resource_books,slug'],
            'name' => ['required', 'string', 'max:255'],
            'language' => ['required', 'string', Rule::in(array_map(
                static fn (Language $l): string => $l->value,
                Language::cases(),
            ))],
            'description' => ['nullable', 'string'],
            'cover_image_url' => ['nullable', 'string', 'max:255'],
            'author' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function toData(): ResourceBookData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return ResourceBookData::from($validated);
    }
}

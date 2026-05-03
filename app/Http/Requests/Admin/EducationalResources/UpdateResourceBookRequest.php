<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\EducationalResources;

use App\Domain\EducationalResources\Models\ResourceBook;
use App\Domain\Shared\Enums\Language;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateResourceBookRequest extends FormRequest
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
        $book = $this->route('book');
        $bookId = $book instanceof ResourceBook ? $book->id : null;

        return [
            'slug' => [
                'sometimes',
                'string',
                'max:255',
                'regex:/^[a-z0-9-]+$/',
                Rule::unique('resource_books', 'slug')->ignore($bookId),
            ],
            'name' => ['sometimes', 'string', 'max:255'],
            'language' => ['sometimes', 'string', Rule::in(array_map(
                static fn (Language $l): string => $l->value,
                Language::cases(),
            ))],
            'description' => ['sometimes', 'nullable', 'string'],
            'cover_image_url' => ['sometimes', 'nullable', 'string', 'max:255'],
            'author' => ['sometimes', 'nullable', 'string', 'max:255'],
            'published_at' => ['sometimes', 'nullable', 'date'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function changes(): array
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return $validated;
    }
}

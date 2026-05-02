<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Commentary;

use App\Domain\Commentary\Models\Commentary;
use App\Domain\Shared\Enums\Language;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateCommentaryRequest extends FormRequest
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
        $commentary = $this->route('commentary');
        $commentaryId = $commentary instanceof Commentary ? $commentary->id : null;

        return [
            'slug' => [
                'sometimes',
                'string',
                'max:255',
                'regex:/^[a-z0-9-]+$/',
                Rule::unique('commentaries', 'slug')->ignore($commentaryId),
            ],
            'name' => ['sometimes', 'array'],
            'name.*' => ['required', 'string', 'max:255'],
            'abbreviation' => ['sometimes', 'string', 'max:32'],
            'language' => ['sometimes', 'string', Rule::in(array_map(
                static fn (Language $l): string => $l->value,
                Language::cases(),
            ))],
            'source_commentary_id' => [
                'sometimes',
                'nullable',
                'integer',
                'exists:commentaries,id',
            ],
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

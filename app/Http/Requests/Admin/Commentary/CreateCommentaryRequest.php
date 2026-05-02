<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Commentary;

use App\Domain\Commentary\DataTransferObjects\CommentaryData;
use App\Domain\Shared\Enums\Language;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CreateCommentaryRequest extends FormRequest
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
            'slug' => ['nullable', 'string', 'max:255', 'alpha_dash', 'unique:commentaries,slug'],
            'name' => ['required', 'array'],
            'name.*' => ['required', 'string', 'max:255'],
            'abbreviation' => ['required', 'string', 'max:32'],
            'language' => ['required', 'string', Rule::in(array_map(
                static fn (Language $l): string => $l->value,
                Language::cases(),
            ))],
            'source_commentary_id' => ['nullable', 'integer', 'exists:commentaries,id'],
        ];
    }

    public function toData(): CommentaryData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return CommentaryData::from($validated);
    }
}

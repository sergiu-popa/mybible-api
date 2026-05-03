<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\EducationalResources;

use App\Domain\Shared\Enums\Language;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ReorderResourceBooksRequest extends FormRequest
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
            'language' => ['required', 'string', Rule::in(array_map(
                static fn (Language $l): string => $l->value,
                Language::cases(),
            ))],
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct', 'min:1'],
        ];
    }

    public function language(): Language
    {
        return Language::from((string) $this->validated('language'));
    }

    /**
     * @return list<int>
     */
    public function ids(): array
    {
        /** @var list<int> $ids */
        $ids = $this->validated('ids');

        return $ids;
    }
}

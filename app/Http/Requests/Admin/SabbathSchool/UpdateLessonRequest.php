<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\SabbathSchool;

use App\Domain\SabbathSchool\DataTransferObjects\UpdateLessonData;
use App\Domain\Shared\Enums\Language;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateLessonRequest extends FormRequest
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
            'language' => ['sometimes', 'string', Rule::in(array_map(
                static fn (Language $l): string => $l->value,
                Language::cases(),
            ))],
            'age_group' => ['sometimes', 'string', 'max:50'],
            'title' => ['sometimes', 'string', 'max:255'],
            'number' => ['sometimes', 'integer', 'min:1', 'max:65535'],
            'date_from' => ['sometimes', 'date'],
            'date_to' => ['sometimes', 'date', 'after_or_equal:date_from'],
            'trimester_id' => ['sometimes', 'nullable', 'integer', 'exists:sabbath_school_trimesters,id'],
            'memory_verse' => ['sometimes', 'nullable', 'string'],
            'image_cdn_url' => ['sometimes', 'nullable', 'string', 'url', 'max:65535'],
            'published_at' => ['sometimes', 'nullable', 'date'],
        ];
    }

    public function toData(): UpdateLessonData
    {
        /** @var array<string, mixed> $data */
        $data = $this->validated();

        return UpdateLessonData::from($data);
    }
}

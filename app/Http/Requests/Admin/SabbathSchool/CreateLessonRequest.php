<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\SabbathSchool;

use App\Domain\SabbathSchool\DataTransferObjects\LessonData;
use App\Domain\Shared\Enums\Language;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CreateLessonRequest extends FormRequest
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
            'language' => ['required', 'string', Rule::in(array_map(
                static fn (Language $l): string => $l->value,
                Language::cases(),
            ))],
            'age_group' => ['required', 'string', 'max:50'],
            'title' => ['required', 'string', 'max:255'],
            'number' => ['required', 'integer', 'min:1', 'max:65535'],
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
            'trimester_id' => ['nullable', 'integer', 'exists:sabbath_school_trimesters,id'],
            'memory_verse' => ['nullable', 'string'],
            'image_cdn_url' => ['nullable', 'string', 'url', 'max:65535'],
            'published_at' => ['nullable', 'date'],
        ];
    }

    public function toData(): LessonData
    {
        /** @var array<string, mixed> $data */
        $data = $this->validated();

        return LessonData::from($data);
    }
}

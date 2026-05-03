<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\SabbathSchool;

use App\Domain\SabbathSchool\DataTransferObjects\UpdateTrimesterData;
use App\Domain\Shared\Enums\Language;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateTrimesterRequest extends FormRequest
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
            'year' => ['sometimes', 'string', 'size:4'],
            'language' => ['sometimes', 'string', Rule::in(array_map(
                static fn (Language $l): string => $l->value,
                Language::cases(),
            ))],
            'age_group' => ['sometimes', 'string', 'max:50'],
            'title' => ['sometimes', 'string', 'max:128'],
            'number' => ['sometimes', 'integer', 'min:1', 'max:32767'],
            'date_from' => ['sometimes', 'date'],
            'date_to' => ['sometimes', 'date', 'after_or_equal:date_from'],
            'image_cdn_url' => ['sometimes', 'nullable', 'string', 'url', 'max:65535'],
        ];
    }

    public function toData(): UpdateTrimesterData
    {
        /** @var array<string, mixed> $data */
        $data = $this->validated();

        return UpdateTrimesterData::from($data);
    }
}

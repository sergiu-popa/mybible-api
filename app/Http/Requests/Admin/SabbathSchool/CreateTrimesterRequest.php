<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\SabbathSchool;

use App\Domain\SabbathSchool\DataTransferObjects\TrimesterData;
use App\Domain\Shared\Enums\Language;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CreateTrimesterRequest extends FormRequest
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
            'year' => ['required', 'string', 'size:4'],
            'language' => ['required', 'string', Rule::in(array_map(
                static fn (Language $l): string => $l->value,
                Language::cases(),
            ))],
            'age_group' => ['required', 'string', 'max:50'],
            'title' => ['required', 'string', 'max:128'],
            'number' => ['required', 'integer', 'min:1', 'max:32767'],
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
            'image_cdn_url' => ['nullable', 'string', 'url', 'max:65535'],
        ];
    }

    public function toData(): TrimesterData
    {
        /** @var array<string, mixed> $data */
        $data = $this->validated();

        return TrimesterData::from($data);
    }
}

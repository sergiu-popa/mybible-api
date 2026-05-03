<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\SabbathSchool;

use App\Domain\SabbathSchool\DataTransferObjects\UpdateSegmentContentData;
use App\Domain\SabbathSchool\Support\SegmentContentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateSegmentContentRequest extends FormRequest
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
            'type' => ['sometimes', 'string', Rule::in(SegmentContentType::values())],
            'title' => ['sometimes', 'nullable', 'string', 'max:128'],
            'position' => ['sometimes', 'integer', 'min:0'],
            'content' => ['sometimes', 'string'],
        ];
    }

    public function toData(): UpdateSegmentContentData
    {
        /** @var array<string, mixed> $data */
        $data = $this->validated();

        return UpdateSegmentContentData::from($data);
    }
}

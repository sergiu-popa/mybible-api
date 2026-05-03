<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\SabbathSchool;

use App\Domain\SabbathSchool\DataTransferObjects\SegmentContentData;
use App\Domain\SabbathSchool\Support\SegmentContentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CreateSegmentContentRequest extends FormRequest
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
            'type' => ['required', 'string', Rule::in(SegmentContentType::values())],
            'title' => ['nullable', 'string', 'max:128'],
            'position' => ['nullable', 'integer', 'min:0'],
            'content' => ['required', 'string'],
        ];
    }

    public function toData(): SegmentContentData
    {
        /** @var array<string, mixed> $data */
        $data = $this->validated();

        return SegmentContentData::from($data);
    }
}

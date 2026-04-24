<?php

declare(strict_types=1);

namespace App\Http\Requests\SabbathSchool;

use Illuminate\Foundation\Http\FormRequest;

final class ListSabbathSchoolHighlightsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'segment_id' => ['required', 'integer', 'exists:sabbath_school_segments,id'],
        ];
    }

    public function segmentId(): int
    {
        /** @var int|string $value */
        $value = $this->validated('segment_id');

        return (int) $value;
    }
}

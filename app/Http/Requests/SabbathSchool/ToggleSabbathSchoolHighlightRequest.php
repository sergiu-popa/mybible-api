<?php

declare(strict_types=1);

namespace App\Http\Requests\SabbathSchool;

use App\Domain\SabbathSchool\DataTransferObjects\ToggleSabbathSchoolHighlightData;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

final class ToggleSabbathSchoolHighlightRequest extends FormRequest
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
            'segment_content_id' => ['required', 'integer', 'exists:sabbath_school_segment_contents,id'],
            'start_position' => ['required', 'integer', 'min:0'],
            'end_position' => ['required', 'integer', 'gt:start_position'],
            'color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}([0-9A-Fa-f]{2})?$/'],
        ];
    }

    public function toData(): ToggleSabbathSchoolHighlightData
    {
        /** @var array{segment_content_id: int, start_position: int, end_position: int, color: string} $data */
        $data = $this->validated();

        /** @var User $user */
        $user = $this->user();

        return new ToggleSabbathSchoolHighlightData(
            user: $user,
            segmentContentId: (int) $data['segment_content_id'],
            startPosition: (int) $data['start_position'],
            endPosition: (int) $data['end_position'],
            color: $data['color'],
        );
    }
}

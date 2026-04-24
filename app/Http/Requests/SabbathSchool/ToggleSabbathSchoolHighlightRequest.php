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
            'segment_id' => ['required', 'integer', 'exists:sabbath_school_segments,id'],
            // Full canonical parsing happens inside the Action so a parse
            // failure can produce the domain-specific 422 envelope. Keep the
            // validator fast — just confirm the field is a non-empty string.
            'passage' => ['required', 'string', 'min:1'],
        ];
    }

    public function toData(): ToggleSabbathSchoolHighlightData
    {
        /** @var array{segment_id: int, passage: string} $data */
        $data = $this->validated();

        /** @var User $user */
        $user = $this->user();

        return new ToggleSabbathSchoolHighlightData(
            user: $user,
            segmentId: (int) $data['segment_id'],
            passage: $data['passage'],
        );
    }
}

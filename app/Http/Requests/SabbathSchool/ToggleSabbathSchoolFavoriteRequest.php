<?php

declare(strict_types=1);

namespace App\Http\Requests\SabbathSchool;

use App\Domain\SabbathSchool\DataTransferObjects\ToggleSabbathSchoolFavoriteData;
use App\Domain\SabbathSchool\Models\SabbathSchoolSegment;
use App\Models\User;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

final class ToggleSabbathSchoolFavoriteRequest extends FormRequest
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
            'lesson_id' => ['required', 'integer', 'exists:sabbath_school_lessons,id'],
            'segment_id' => ['nullable', 'integer', 'exists:sabbath_school_segments,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            /** @var array{lesson_id: int, segment_id?: int|null} $data */
            $data = $validator->validated();

            if (($data['segment_id'] ?? null) === null) {
                return;
            }

            $segment = SabbathSchoolSegment::query()->find($data['segment_id']);

            if ($segment === null) {
                return;
            }

            if ($segment->sabbath_school_lesson_id !== (int) $data['lesson_id']) {
                $validator->errors()->add(
                    'segment_id',
                    'The selected segment does not belong to the given lesson.',
                );
            }
        });
    }

    public function toData(): ToggleSabbathSchoolFavoriteData
    {
        /** @var array{lesson_id: int, segment_id?: int|null} $data */
        $data = $this->validated();

        /** @var User $user */
        $user = $this->user();

        $segmentId = $data['segment_id'] ?? null;

        return new ToggleSabbathSchoolFavoriteData(
            user: $user,
            lessonId: (int) $data['lesson_id'],
            segmentId: $segmentId === null ? null : (int) $segmentId,
        );
    }
}

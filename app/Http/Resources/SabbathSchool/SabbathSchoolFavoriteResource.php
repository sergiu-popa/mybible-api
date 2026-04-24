<?php

declare(strict_types=1);

namespace App\Http\Resources\SabbathSchool;

use App\Domain\SabbathSchool\Models\SabbathSchoolFavorite;
use App\Domain\SabbathSchool\Support\SabbathSchoolFavoriteSentinel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SabbathSchoolFavorite
 */
final class SabbathSchoolFavoriteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $segmentId = $this->sabbath_school_segment_id;
        $isWholeLesson = $segmentId === SabbathSchoolFavoriteSentinel::WHOLE_LESSON;

        return [
            'id' => $this->id,
            'lesson_id' => $this->sabbath_school_lesson_id,
            // Map the `0` sentinel to `null` so clients do not have to know
            // the sentinel value. Non-zero ids surface untouched.
            'segment_id' => $isWholeLesson ? null : $segmentId,
            'whole_lesson' => $isWholeLesson,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}

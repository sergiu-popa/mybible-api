<?php

declare(strict_types=1);

namespace App\Http\Resources\SabbathSchool;

use App\Domain\SabbathSchool\Models\SabbathSchoolFavorite;
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
        $isWholeLesson = $segmentId === null;

        return [
            'id' => $this->id,
            'lesson_id' => $this->sabbath_school_lesson_id,
            'segment_id' => $segmentId,
            'whole_lesson' => $isWholeLesson,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}

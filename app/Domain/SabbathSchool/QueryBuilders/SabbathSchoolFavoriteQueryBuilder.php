<?php

declare(strict_types=1);

namespace App\Domain\SabbathSchool\QueryBuilders;

use App\Domain\SabbathSchool\Models\SabbathSchoolFavorite;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends Builder<SabbathSchoolFavorite>
 */
final class SabbathSchoolFavoriteQueryBuilder extends Builder
{
    public function forUser(User $user): self
    {
        return $this->where('user_id', $user->id);
    }

    public function forLessonAndSegment(int $lessonId, ?int $segmentId): self
    {
        $query = $this->where('sabbath_school_lesson_id', $lessonId);

        return $segmentId === null
            ? $query->whereNull('sabbath_school_segment_id')
            : $query->where('sabbath_school_segment_id', $segmentId);
    }

    public function forWholeLesson(int $lessonId): self
    {
        return $this
            ->where('sabbath_school_lesson_id', $lessonId)
            ->whereNull('sabbath_school_segment_id');
    }
}

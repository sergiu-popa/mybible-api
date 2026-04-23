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

    public function forLessonAndSegment(int $lessonId, int $segmentId): self
    {
        return $this
            ->where('sabbath_school_lesson_id', $lessonId)
            ->where('sabbath_school_segment_id', $segmentId);
    }
}

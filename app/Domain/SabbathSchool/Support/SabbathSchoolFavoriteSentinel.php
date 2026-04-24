<?php

declare(strict_types=1);

namespace App\Domain\SabbathSchool\Support;

/**
 * Sentinel values for the `sabbath_school_favorites.sabbath_school_segment_id`
 * column.
 *
 * The schema keeps the column NOT NULL (default 0) so the composite unique
 * index `(user_id, lesson_id, segment_id)` behaves correctly under MySQL —
 * nullable columns would let the same lesson-level favorite be inserted
 * multiple times per user.
 */
final class SabbathSchoolFavoriteSentinel
{
    /**
     * Sentinel meaning "the entire lesson is favorited", as opposed to a
     * specific segment inside it.
     */
    public const int WHOLE_LESSON = 0;
}

<?php

declare(strict_types=1);

namespace App\Domain\SabbathSchool\DataTransferObjects;

use App\Domain\SabbathSchool\Support\SabbathSchoolFavoriteSentinel;
use App\Models\User;

final readonly class ToggleSabbathSchoolFavoriteData
{
    /**
     * @param  int  $segmentId  Use {@see SabbathSchoolFavoriteSentinel::WHOLE_LESSON}
     *                          when the user is favoriting the whole lesson (no
     *                          specific segment).
     */
    public function __construct(
        public User $user,
        public int $lessonId,
        public int $segmentId,
    ) {}
}

<?php

declare(strict_types=1);

namespace App\Domain\SabbathSchool\DataTransferObjects;

use App\Models\User;

final readonly class ToggleSabbathSchoolFavoriteData
{
    /**
     * @param  ?int  $segmentId  null = whole-lesson favorite.
     */
    public function __construct(
        public User $user,
        public int $lessonId,
        public ?int $segmentId,
    ) {}
}

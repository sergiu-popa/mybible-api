<?php

declare(strict_types=1);

namespace App\Domain\SabbathSchool\DataTransferObjects;

use App\Models\User;

final readonly class ToggleSabbathSchoolHighlightData
{
    public function __construct(
        public User $user,
        public int $segmentContentId,
        public int $startPosition,
        public int $endPosition,
        public string $color,
    ) {}
}

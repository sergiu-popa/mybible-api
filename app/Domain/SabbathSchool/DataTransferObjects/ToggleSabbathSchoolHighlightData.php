<?php

declare(strict_types=1);

namespace App\Domain\SabbathSchool\DataTransferObjects;

use App\Models\User;

final readonly class ToggleSabbathSchoolHighlightData
{
    public function __construct(
        public User $user,
        public int $segmentId,
        public string $passage,
    ) {}
}

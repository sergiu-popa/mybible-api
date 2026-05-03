<?php

declare(strict_types=1);

namespace App\Domain\SabbathSchool\DataTransferObjects;

use App\Domain\SabbathSchool\Models\SabbathSchoolSegmentContent;
use App\Models\User;

final readonly class UpsertSabbathSchoolAnswerData
{
    public function __construct(
        public User $user,
        public SabbathSchoolSegmentContent $segmentContent,
        public string $content,
    ) {}
}

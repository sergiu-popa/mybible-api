<?php

declare(strict_types=1);

namespace App\Domain\SabbathSchool\DataTransferObjects;

use App\Domain\SabbathSchool\Models\SabbathSchoolQuestion;
use App\Models\User;

final readonly class UpsertSabbathSchoolAnswerData
{
    public function __construct(
        public User $user,
        public SabbathSchoolQuestion $question,
        public string $content,
    ) {}
}

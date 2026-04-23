<?php

declare(strict_types=1);

namespace App\Domain\SabbathSchool\DataTransferObjects;

use App\Domain\SabbathSchool\Models\SabbathSchoolAnswer;

final readonly class UpsertSabbathSchoolAnswerResult
{
    public function __construct(
        public SabbathSchoolAnswer $answer,
        public bool $created,
    ) {}
}

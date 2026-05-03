<?php

declare(strict_types=1);

namespace App\Domain\SabbathSchool\DataTransferObjects;

final readonly class PatchSabbathSchoolHighlightData
{
    public function __construct(
        public string $color,
    ) {}
}

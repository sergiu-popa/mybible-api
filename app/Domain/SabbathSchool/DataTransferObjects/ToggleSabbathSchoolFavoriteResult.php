<?php

declare(strict_types=1);

namespace App\Domain\SabbathSchool\DataTransferObjects;

use App\Domain\SabbathSchool\Models\SabbathSchoolFavorite;

final readonly class ToggleSabbathSchoolFavoriteResult
{
    public function __construct(
        public ?SabbathSchoolFavorite $favorite,
        public bool $created,
    ) {}

    public static function created(SabbathSchoolFavorite $favorite): self
    {
        return new self($favorite, true);
    }

    public static function deleted(): self
    {
        return new self(null, false);
    }
}

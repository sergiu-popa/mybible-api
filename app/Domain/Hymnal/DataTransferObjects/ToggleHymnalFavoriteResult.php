<?php

declare(strict_types=1);

namespace App\Domain\Hymnal\DataTransferObjects;

use App\Domain\Hymnal\Models\HymnalFavorite;

final readonly class ToggleHymnalFavoriteResult
{
    public function __construct(
        public HymnalFavorite $favorite,
        public bool $created,
    ) {}
}

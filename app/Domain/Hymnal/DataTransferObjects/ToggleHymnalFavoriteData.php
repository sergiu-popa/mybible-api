<?php

declare(strict_types=1);

namespace App\Domain\Hymnal\DataTransferObjects;

use App\Domain\Hymnal\Models\HymnalSong;
use App\Models\User;

final readonly class ToggleHymnalFavoriteData
{
    public function __construct(
        public User $user,
        public HymnalSong $song,
    ) {}
}

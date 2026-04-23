<?php

declare(strict_types=1);

namespace App\Domain\Devotional\DataTransferObjects;

use App\Models\User;

final readonly class ToggleDevotionalFavoriteData
{
    public function __construct(
        public User $user,
        public int $devotionalId,
    ) {}
}

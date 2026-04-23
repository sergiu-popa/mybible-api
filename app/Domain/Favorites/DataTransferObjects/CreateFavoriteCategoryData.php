<?php

declare(strict_types=1);

namespace App\Domain\Favorites\DataTransferObjects;

use App\Models\User;

final readonly class CreateFavoriteCategoryData
{
    public function __construct(
        public User $user,
        public string $name,
        public ?string $color,
    ) {}
}

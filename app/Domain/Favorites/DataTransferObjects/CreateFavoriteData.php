<?php

declare(strict_types=1);

namespace App\Domain\Favorites\DataTransferObjects;

use App\Domain\Favorites\Models\FavoriteCategory;
use App\Domain\Reference\Reference;
use App\Models\User;

final readonly class CreateFavoriteData
{
    public function __construct(
        public User $user,
        public Reference $reference,
        public ?FavoriteCategory $category,
        public ?string $note,
        public ?string $color,
    ) {}
}

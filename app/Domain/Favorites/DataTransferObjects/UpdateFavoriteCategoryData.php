<?php

declare(strict_types=1);

namespace App\Domain\Favorites\DataTransferObjects;

use App\Domain\Favorites\Models\FavoriteCategory;

final readonly class UpdateFavoriteCategoryData
{
    public function __construct(
        public FavoriteCategory $category,
        public ?string $name,
        public bool $nameProvided,
        public ?string $color,
        public bool $colorProvided,
    ) {}
}

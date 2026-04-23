<?php

declare(strict_types=1);

namespace App\Domain\Favorites\DataTransferObjects;

use App\Domain\Favorites\Models\Favorite;

final readonly class UpdateFavoriteData
{
    public function __construct(
        public Favorite $favorite,
        public ?int $categoryId,
        public bool $categoryProvided,
        public ?string $note,
        public bool $noteProvided,
    ) {}
}

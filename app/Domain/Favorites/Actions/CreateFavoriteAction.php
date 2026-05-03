<?php

declare(strict_types=1);

namespace App\Domain\Favorites\Actions;

use App\Domain\Favorites\DataTransferObjects\CreateFavoriteData;
use App\Domain\Favorites\Models\Favorite;
use App\Domain\Reference\Formatter\ReferenceFormatter;

final class CreateFavoriteAction
{
    public function __construct(
        private readonly ReferenceFormatter $formatter,
    ) {}

    public function execute(CreateFavoriteData $data): Favorite
    {
        return Favorite::query()->create([
            'user_id' => $data->user->id,
            'category_id' => $data->category?->id,
            'reference' => $this->formatter->toCanonical($data->reference),
            'note' => $data->note,
            'color' => $data->color,
        ]);
    }
}

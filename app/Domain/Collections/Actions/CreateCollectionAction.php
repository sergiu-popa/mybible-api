<?php

declare(strict_types=1);

namespace App\Domain\Collections\Actions;

use App\Domain\Collections\DataTransferObjects\CreateCollectionData;
use App\Domain\Collections\Models\Collection;

final class CreateCollectionAction
{
    public function handle(CreateCollectionData $data): Collection
    {
        return Collection::query()->create([
            'slug' => $data->slug,
            'name' => $data->name,
            'language' => $data->language,
            'position' => $data->position,
        ]);
    }
}

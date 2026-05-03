<?php

declare(strict_types=1);

namespace App\Domain\Collections\Actions;

use App\Domain\Collections\Models\Collection;
use App\Http\Resources\Collections\CollectionDetailResource;

final class ShowCollectionAction
{
    public function handle(Collection $collection): CollectionDetailResource
    {
        $collection->load(['topics' => fn ($query) => $query->orderBy('position')]);

        return new CollectionDetailResource($collection);
    }
}

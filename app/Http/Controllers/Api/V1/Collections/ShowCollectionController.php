<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Collections;

use App\Domain\Collections\Actions\ShowCollectionAction;
use App\Domain\Collections\Models\Collection;
use App\Http\Requests\Collections\ShowCollectionRequest;
use App\Http\Resources\Collections\CollectionDetailResource;

/**
 * @tags Collections
 */
final class ShowCollectionController
{
    public function __invoke(
        ShowCollectionRequest $request,
        Collection $collection,
        ShowCollectionAction $action,
    ): CollectionDetailResource {
        return $action->handle($collection);
    }
}

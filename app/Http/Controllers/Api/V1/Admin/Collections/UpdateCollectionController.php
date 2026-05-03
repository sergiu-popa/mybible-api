<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Collections;

use App\Domain\Collections\Actions\UpdateCollectionAction;
use App\Domain\Collections\Models\Collection;
use App\Http\Requests\Admin\Collections\UpdateCollectionRequest;
use App\Http\Resources\Collections\CollectionResource;

final class UpdateCollectionController
{
    public function __invoke(
        UpdateCollectionRequest $request,
        Collection $collection,
        UpdateCollectionAction $action,
    ): CollectionResource {
        return CollectionResource::make($action->handle($collection, $request->toData()));
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Collections;

use App\Domain\Collections\Actions\CreateCollectionAction;
use App\Http\Requests\Admin\Collections\CreateCollectionRequest;
use App\Http\Resources\Collections\CollectionResource;
use Illuminate\Http\JsonResponse;

final class CreateCollectionController
{
    public function __invoke(
        CreateCollectionRequest $request,
        CreateCollectionAction $action,
    ): JsonResponse {
        $collection = $action->handle($request->toData());

        return CollectionResource::make($collection)
            ->response()
            ->setStatusCode(201);
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Collections;

use App\Domain\Collections\Actions\CreateCollectionTopicAction;
use App\Domain\Collections\Models\Collection;
use App\Http\Requests\Admin\Collections\CreateCollectionTopicRequest;
use App\Http\Resources\Collections\CollectionTopicResource;
use Illuminate\Http\JsonResponse;

final class CreateCollectionTopicController
{
    public function __invoke(
        CreateCollectionTopicRequest $request,
        Collection $collection,
        CreateCollectionTopicAction $action,
    ): JsonResponse {
        $topic = $action->handle($request->toData());

        return CollectionTopicResource::make($topic)
            ->response()
            ->setStatusCode(201);
    }
}

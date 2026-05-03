<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Collections;

use App\Domain\Collections\Actions\UpdateCollectionTopicAction;
use App\Domain\Collections\Models\Collection;
use App\Domain\Collections\Models\CollectionTopic;
use App\Http\Requests\Admin\Collections\UpdateCollectionTopicRequest;
use App\Http\Resources\Collections\CollectionTopicResource;

final class UpdateCollectionTopicController
{
    public function __invoke(
        UpdateCollectionTopicRequest $request,
        Collection $collection,
        CollectionTopic $topic,
        UpdateCollectionTopicAction $action,
    ): CollectionTopicResource {
        return CollectionTopicResource::make($action->handle($topic, $request->toData()));
    }
}

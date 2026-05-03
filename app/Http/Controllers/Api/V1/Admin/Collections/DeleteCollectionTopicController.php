<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Collections;

use App\Domain\Collections\Actions\DeleteCollectionTopicAction;
use App\Domain\Collections\Models\Collection;
use App\Domain\Collections\Models\CollectionTopic;
use App\Http\Requests\Admin\Collections\DeleteCollectionTopicRequest;
use Illuminate\Http\Response;

final class DeleteCollectionTopicController
{
    public function __invoke(
        DeleteCollectionTopicRequest $request,
        Collection $collection,
        CollectionTopic $topic,
        DeleteCollectionTopicAction $action,
    ): Response {
        $action->handle($topic);

        return response()->noContent();
    }
}

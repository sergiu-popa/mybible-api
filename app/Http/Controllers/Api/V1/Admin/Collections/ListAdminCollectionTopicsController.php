<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Collections;

use App\Domain\Collections\Models\Collection;
use App\Domain\Collections\Models\CollectionTopic;
use App\Http\Requests\Admin\Collections\ListAdminCollectionTopicsRequest;
use App\Http\Resources\Collections\CollectionTopicResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ListAdminCollectionTopicsController
{
    public function __invoke(
        ListAdminCollectionTopicsRequest $request,
        Collection $collection,
    ): AnonymousResourceCollection {
        $paginator = CollectionTopic::query()
            ->withinCollection($collection->id)
            ->ordered()
            ->paginate($request->perPage(), page: $request->pageNumber());

        return CollectionTopicResource::collection($paginator);
    }
}
